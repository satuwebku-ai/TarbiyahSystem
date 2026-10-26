<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnitAdminProvisioner;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Buat akun admin per unit di struktur organisasi. Hanya Super Admin: akun yang dibuat memegang peran inti organisasi. */
class UnitAdminProvisioningController extends Controller
{
    public function index(Request $request, UnitAdminProvisioner $provisioner): View
    {
        $this->authorizeSuperAdmin($request);
        $input = $this->input($request);

        return view('admin.admins.provision', [
            'plan' => $provisioner->plan($input['types'], $input['email_domain']),
            'types' => $input['types'], 'emailDomain' => $input['email_domain'],
        ]);
    }

    public function store(Request $request, UnitAdminProvisioner $provisioner): Response
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Centang pernyataan bahwa Anda memahami kata sandi hanya ditampilkan sekali.']);
        $input = $this->input($request);

        // Rencana dihitung ulang saat ini juga, bukan dipercaya dari form: unit/akun bisa berubah sejak pratinjau.
        $created = $provisioner->provision(
            $provisioner->plan($input['types'], $input['email_domain']),
            $request->user('admin'),
            ! $request->boolean('inactive'),
        );

        return response()->view('admin.admins.provision-result', ['created' => $created])
            ->header('Cache-Control', 'no-store, private');
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user('admin')->isSuperAdmin(), 403, 'Hanya Super Admin yang dapat membuat akun per unit.');
    }

    /** @return array{types: list<string>, email_domain: ?string} */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:'.implode(',', array_keys(UnitAdminProvisioner::ROLE_BY_TYPE))],
            'email_domain' => ['nullable', 'string', 'max:100', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ], ['email_domain.regex' => 'Domain email tidak valid (contoh: sekolah.sch.id).']);

        return [
            'types' => array_values($data['types'] ?? array_keys(UnitAdminProvisioner::ROLE_BY_TYPE)) ?: array_keys(UnitAdminProvisioner::ROLE_BY_TYPE),
            'email_domain' => filled($data['email_domain'] ?? null) ? $data['email_domain'] : null,
        ];
    }
}
