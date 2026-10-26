<?php

namespace Tests\Feature;

use App\Services\Face\AwsRekognitionFaceMatcher;
use App\Services\Face\FaceMatchException;
use App\Services\Face\FaceMatcherFactory;
use App\Services\Face\HttpFaceMatcher;
use App\Services\Face\NoFaceDetectedException;
use App\Services\Face\NullFaceMatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceMatcherDriversTest extends TestCase
{
    public function test_factory_picks_driver_from_config_and_defaults_to_off(): void
    {
        $this->assertInstanceOf(NullFaceMatcher::class, FaceMatcherFactory::make('off'));
        $this->assertInstanceOf(NullFaceMatcher::class, FaceMatcherFactory::make('tidak-dikenal'));
        $this->assertInstanceOf(HttpFaceMatcher::class, FaceMatcherFactory::make('http'));
        $this->assertInstanceOf(AwsRekognitionFaceMatcher::class, FaceMatcherFactory::make('aws'));

        config(['face.driver' => 'off']);
        $this->assertFalse(FaceMatcherFactory::enabled());
        config(['face.driver' => 'http']);
        $this->assertTrue(FaceMatcherFactory::enabled());
    }

    public function test_http_driver_sends_both_photos_with_bearer_token_and_returns_clamped_score(): void
    {
        Http::fake(['127.0.0.1:8099/*' => Http::response(['similarity' => 123.4])]);

        $score = (new HttpFaceMatcher('http://127.0.0.1:8099/compare', 'rahasia'))->compare('REF', 'PROBE');

        $this->assertSame(100.0, $score);
        Http::assertSent(function (Request $r) {
            return $r->hasHeader('Authorization', 'Bearer rahasia')
                && $r->method() === 'POST'
                && str_contains($r->body(), 'REF') && str_contains($r->body(), 'PROBE');
        });
    }

    public function test_http_driver_maps_no_face_and_failures(): void
    {
        Http::fake([
            'wajah.example.test/nf' => Http::response(['error' => 'no_face'], 422),
            'wajah.example.test/boom' => Http::response('x', 500),
            'wajah.example.test/bad' => Http::response(['halo' => 1]),
        ]);

        $this->expectException(NoFaceDetectedException::class);
        (new HttpFaceMatcher('https://wajah.example.test/nf', 't'))->compare('a', 'b');
    }

    public function test_http_driver_server_error_and_malformed_reply_are_failures(): void
    {
        Http::fake([
            'wajah.example.test/boom' => Http::response('x', 500),
            'wajah.example.test/bad' => Http::response(['halo' => 1]),
        ]);

        foreach (['boom', 'bad'] as $path) {
            try {
                (new HttpFaceMatcher("https://wajah.example.test/$path", 't'))->compare('a', 'b');
                $this->fail('Seharusnya gagal');
            } catch (FaceMatchException $e) {
                $this->assertNotInstanceOf(NoFaceDetectedException::class, $e);
            }
        }
    }

    public function test_http_driver_refuses_plain_http_to_remote_hosts_missing_url_or_token(): void
    {
        Http::fake();

        foreach ([
            ['http://wajah.example.test/compare', 't'], // http polos ke luar server
            [null, 't'],
            ['ftp://127.0.0.1/compare', 't'],
            ['https://wajah.example.test/compare', ''], // tanpa token
        ] as [$url, $token]) {
            try {
                (new HttpFaceMatcher($url, $token))->compare('a', 'b');
                $this->fail("Seharusnya ditolak: $url");
            } catch (FaceMatchException) {
                $this->assertTrue(true);
            }
        }
        Http::assertNothingSent();
    }

    public function test_aws_driver_returns_best_similarity_zero_without_match_and_maps_no_face(): void
    {
        $client = new class
        {
            public array $calls = [];

            public mixed $reply = ['FaceMatches' => [['Similarity' => 88.2], ['Similarity' => 93.1]]];

            public function compareFaces(array $args): array
            {
                $this->calls[] = $args;
                if ($this->reply instanceof \Throwable) {
                    throw $this->reply;
                }

                return $this->reply;
            }
        };
        $matcher = new AwsRekognitionFaceMatcher('ap-southeast-1', 'k', 's', $client);

        $this->assertSame(93.1, $matcher->compare('REF', 'PROBE'));
        $this->assertSame('REF', $client->calls[0]['SourceImage']['Bytes']);
        $this->assertSame('PROBE', $client->calls[0]['TargetImage']['Bytes']);

        $client->reply = ['FaceMatches' => []];
        $this->assertSame(0.0, $matcher->compare('REF', 'PROBE'));

        $client->reply = new class('tidak ada wajah') extends \Exception
        {
            public function getAwsErrorCode(): string
            {
                return 'InvalidParameterException';
            }
        };
        $this->expectException(NoFaceDetectedException::class);
        $matcher->compare('REF', 'PROBE');
    }

    public function test_aws_driver_without_sdk_or_credentials_fails_clearly(): void
    {
        $this->expectException(FaceMatchException::class);
        (new AwsRekognitionFaceMatcher('ap-southeast-1', null, null))->compare('a', 'b');
    }
}
