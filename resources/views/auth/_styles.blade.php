@include('layouts._theme')
<style>
    body{background-color:var(--color-neutral-50);background-image:radial-gradient(var(--color-neutral-350) 1px,transparent 1px);
        background-size:20px 20px;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
    .auth-stack{width:100%;max-width:400px;display:flex;flex-direction:column;align-items:center}
    .auth-brand{display:flex;align-items:center;gap:8px;color:var(--color-neutral-900)}
    .auth-logo{width:40px;height:40px;border-radius:50%;background:#1A1D25;color:#fff;display:flex;align-items:center;justify-content:center}
    .auth-card{width:100%;margin-top:24px;background:#fff;border:1px solid var(--color-neutral-200);border-radius:14px;
        box-shadow:var(--shadow-floating);padding:32px}
    .auth-card h1{color:var(--color-neutral-900);margin:0}
    .auth-sub{color:var(--color-neutral-600);margin:4px 0 24px}
    .auth-field{margin-bottom:16px}
    .auth-field label{display:block;color:var(--color-neutral-900);margin-bottom:6px}
    .auth-req{color:var(--red-700);margin-left:2px}
    .auth-input{position:relative}
    .auth-input svg{position:absolute;left:12px;top:12px;color:var(--color-neutral-400);pointer-events:none}
    .auth-input .form-control{height:40px;padding-left:36px}
    .auth-check{display:flex;align-items:center;gap:8px;margin:0;color:var(--color-neutral-600);font-size:14px}
    .auth-check .form-check-input{margin:0;flex-shrink:0}
    .auth-link{color:var(--color-neutral-900);font-weight:500;text-decoration:none}
    .auth-link:hover{text-decoration:underline}
    .auth-submit{width:100%;min-height:40px}
    .auth-helper{text-align:center;margin-top:20px;color:var(--color-neutral-600)}
    .auth-demo{color:var(--color-neutral-400);margin-top:16px;text-align:center}
    .auth-demo strong{font-weight:500;color:var(--color-neutral-600)}
    @media (max-width:479.98px){.auth-card{padding:24px}}
</style>
