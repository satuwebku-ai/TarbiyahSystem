<style>
  /* Bagan: tinggi baris tetap (56px) supaya garis siku selalu tepat di tengah baris */
  .org-tree { --org-line:rgba(128,128,128,.4); }
  .org-tree, .org-tree ul { list-style:none; margin:0; padding:0; }
  .org-tree-item { position:relative; }
  .org-tree-row { display:flex; align-items:center; gap:.65rem; height:56px; padding:0 .5rem; border-radius:.6rem; }
  .org-tree-row:hover { background:rgba(128,128,128,.08); }
  .org-tree-info { flex:1 1 auto; min-width:0; line-height:1.3; }
  .org-tree-info .text-truncate { max-width:100%; }
  .org-tree-icon { width:34px; height:34px; flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; border-radius:.6rem; background:rgba(var(--bs-primary-rgb),.1); color:var(--bs-primary); }
  .org-tree-toggle { width:24px; height:24px; flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; padding:0; border:0; border-radius:50%; background:transparent; color:inherit; }
  button.org-tree-toggle:hover { background:rgba(128,128,128,.18); }
  button.org-tree-toggle svg { transition:transform .15s ease; transform:rotate(90deg); }
  button.org-tree-toggle.collapsed svg { transform:rotate(0deg); }
  .org-tree-dot { width:6px; height:6px; border-radius:50%; background:rgba(128,128,128,.55); }

  /* anak: garis vertikal tepat di bawah panah induk (8px padding + 12px = 20px) */
  .org-tree .org-tree-children { margin-left:20px; padding-left:24px; }
  .org-tree-children > .org-tree-item::before { /* siku: turun dari atas lalu belok ke kanan di tengah baris */
    content:""; position:absolute; left:-24px; top:0; width:28px; height:28px;
    border-left:1px solid var(--org-line); border-bottom:1px solid var(--org-line); border-bottom-left-radius:8px;
  }
  .org-tree-children > .org-tree-item:not(:last-child)::after { /* lanjutan garis ke saudara berikutnya */
    content:""; position:absolute; left:-24px; top:28px; bottom:0; border-left:1px solid var(--org-line);
  }
  .org-tree-item.org-hide { display:none; }
  .org-add-btn { flex:0 0 auto; display:inline-flex; align-items:center; white-space:nowrap; }
  @media (max-width:575.98px) {
    .org-tree .org-tree-children { margin-left:16px; padding-left:16px; }
    .org-tree-children > .org-tree-item::before { left:-16px; width:20px; }
    .org-tree-children > .org-tree-item:not(:last-child)::after { left:-16px; }
  }
</style>
