<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
    --color-neutral-0:#FFFFFF;--color-neutral-50:#F5F7F9;--color-neutral-100:#EFF1F3;--color-neutral-200:#E2E4E6;
    --color-neutral-350:#D2D5D9;--color-neutral-400:#9BA0A6;--color-neutral-600:#4E525A;--color-neutral-700:#3B3F4B;
    --color-neutral-850:#1B1E28;--color-neutral-900:#0D0F14;--color-neutral-1000:#1A1D25;
    --color-ink-900:#17191F;--color-ink-800:#262A32;
    --red-50:#FDE8E5;--red-700:#B42318;--amber-50:#FBF0DA;--amber-700:#8F5B06;
    --lime-50:#ECF5DA;--lime-700:#457F19;--green-50:#E2F4E6;--green-700:#147A3A;--green-400:#07B93B;
    color-scheme:light;--text-muted:#61666E;--on-solid:#fff;--bs-warning-rgb:143,91,6;
    --radius-checkbox:5px;--radius-chip:6px;--radius-pill:7px;--radius-control:10px;--radius-surface:14px;
    --shadow-raised:0 2px 5px rgba(13,15,20,.08),0 1px 1px rgba(13,15,20,.05);
    --shadow-floating:0 12px 32px -4px rgba(13,15,20,.22),0 2px 6px rgba(13,15,20,.10);

    --bs-body-font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
    --bs-body-color:var(--color-neutral-600);--bs-body-bg:var(--color-neutral-0);
    --bs-emphasis-color:var(--color-neutral-900);--bs-heading-color:var(--color-neutral-900);
    --bs-secondary-color:var(--text-muted);--bs-tertiary-color:var(--text-muted);
    --bs-border-color:var(--color-neutral-200);--bs-border-radius:10px;--bs-border-radius-lg:14px;--bs-border-radius-sm:7px;
    --bs-link-color:var(--color-neutral-900);--bs-link-hover-color:var(--color-ink-800);
    --bs-link-color-rgb:13,15,20;--bs-link-hover-color-rgb:38,42,50;
    --bs-primary-rgb:23,25,31;--bs-success-rgb:20,122,58;--bs-danger-rgb:180,35,24;--bs-warning-rgb:143,91,6;--bs-info-rgb:78,82,90;
    --bs-secondary-rgb:78,82,90;--bs-dark-rgb:23,25,31;
    --bs-primary-bg-subtle:var(--color-neutral-50);--bs-secondary-bg-subtle:var(--color-neutral-50);--bs-info-bg-subtle:var(--color-neutral-50);
    --bs-success-bg-subtle:var(--green-50);--bs-danger-bg-subtle:var(--red-50);--bs-warning-bg-subtle:var(--amber-50);
    --bs-primary-text-emphasis:var(--color-neutral-900);--bs-info-text-emphasis:var(--color-neutral-700);
    --bs-success-text-emphasis:var(--green-700);--bs-danger-text-emphasis:var(--red-700);--bs-warning-text-emphasis:var(--amber-700);
    --bs-primary-border-subtle:var(--color-neutral-200);--bs-info-border-subtle:var(--color-neutral-200);
    --bs-success-border-subtle:var(--green-50);--bs-danger-border-subtle:var(--red-50);--bs-warning-border-subtle:var(--amber-50);
    --bs-secondary-bg:var(--color-neutral-100);--bs-tertiary-bg:var(--color-neutral-50);
}
body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-feature-settings:"cv11","ss01";
    -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;margin:0;overflow-x:hidden;background:var(--color-neutral-0);color:var(--color-neutral-600)}
h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{color:var(--color-neutral-900);font-weight:600}
:focus-visible{outline:2px solid #17191F;outline-offset:2px}

.text-kpi-value{font-weight:600;font-size:31.5px;line-height:38px;letter-spacing:-0.4px}
.text-heading-page{font-weight:600;font-size:23.5px;line-height:30px;letter-spacing:-0.3px}
.text-heading-brand{font-weight:600;font-size:16px;line-height:22px;letter-spacing:-0.25px}
.text-body-large{font-weight:400;font-size:16px;line-height:22px}
.text-body-medium{font-weight:500;font-size:14px;line-height:20px;letter-spacing:-0.25px}
.text-body-regular{font-weight:400;font-size:14px;line-height:20px;letter-spacing:-0.1px}
.text-body-small{font-weight:400;font-size:12.5px;line-height:18px}
.text-label-tab{font-weight:500;font-size:14px;line-height:20px;letter-spacing:-0.15px}
.text-label-score{font-weight:500;font-size:13px;line-height:18px}
.text-label-caps{font-weight:500;font-size:12px;line-height:16px;letter-spacing:0.5px;text-transform:uppercase}

.text-muted,.text-body-secondary,.text-secondary{color:var(--text-muted)!important}
/* neutral-400 is 2.6:1 on white; content-level muted text uses neutral-600 to stay legible. */
.page-content .text-muted,.page-content .text-secondary,.page-content .text-body-secondary,.form-text{color:var(--color-neutral-600)!important}
.text-success{color:var(--green-700)!important}
.text-danger{color:var(--red-700)!important}
.text-warning{color:var(--amber-700)!important}
.text-primary,.text-dark{color:var(--color-neutral-900)!important}
.text-info{color:var(--color-neutral-600)!important}
.bg-success{background-color:rgba(var(--bs-success-rgb),var(--bs-bg-opacity,1))!important}
.bg-danger{background-color:rgba(var(--bs-danger-rgb),var(--bs-bg-opacity,1))!important}
.bg-warning{background-color:rgba(var(--bs-warning-rgb),var(--bs-bg-opacity,1))!important}
.bg-primary{background-color:rgba(var(--bs-primary-rgb),var(--bs-bg-opacity,1))!important}
.bg-dark{background-color:rgba(var(--bs-dark-rgb),var(--bs-bg-opacity,1))!important}
.bg-info{background-color:rgba(var(--bs-info-rgb),var(--bs-bg-opacity,1))!important}
.bg-secondary{background-color:rgba(var(--bs-secondary-rgb),var(--bs-bg-opacity,1))!important}
.bg-opacity-5{--bs-bg-opacity:.05}
.bg-light{background-color:var(--color-neutral-50)!important}
.bg-white{background-color:var(--color-neutral-0)!important}
.min-w-0{min-width:0}
@media(max-width:767.98px){.page-content form.d-flex,.page-content .d-flex.gap-2:has(>.btn){flex-wrap:wrap}}
.bg-success-subtle{background-color:var(--green-50)!important}
.bg-danger-subtle{background-color:var(--red-50)!important}
.bg-warning-subtle{background-color:var(--amber-50)!important}
.bg-primary-subtle,.bg-info-subtle,.bg-secondary-subtle{background-color:var(--color-neutral-50)!important}
.border{border-color:var(--color-neutral-200)!important}

/* Buttons */
.btn{--bs-btn-padding-x:12px;--bs-btn-padding-y:0;--bs-btn-font-size:14px;--bs-btn-font-weight:500;--bs-btn-border-radius:10px;
    --bs-btn-focus-box-shadow:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;
    min-height:38px;white-space:nowrap;flex-shrink:0;line-height:20px;transition:background-color 120ms ease-out,color 120ms ease-out}
.btn-sm{--bs-btn-padding-x:10px;--bs-btn-font-size:13px;min-height:32px;--bs-btn-border-radius:10px}
.btn-lg{min-height:40px}
.btn .bi{line-height:1}
.btn-group>.btn{flex-shrink:1}
.btn-primary,.btn-success,.btn-dark{--bs-btn-color:#fff;--bs-btn-bg:var(--color-ink-900);--bs-btn-border-color:transparent;
    --bs-btn-hover-color:#fff;--bs-btn-hover-bg:var(--color-ink-800);--bs-btn-hover-border-color:transparent;
    --bs-btn-active-color:#fff;--bs-btn-active-bg:var(--color-ink-800);--bs-btn-active-border-color:transparent;
    --bs-btn-disabled-color:#fff;--bs-btn-disabled-bg:var(--color-ink-900);--bs-btn-disabled-border-color:transparent}
.btn-secondary,.btn-light,.btn-outline-primary,.btn-outline-secondary,.btn-outline-success,.btn-outline-dark,
.btn-outline-info,.btn-outline-warning,.btn-outline-light,.btn-info,.btn-warning{
    --bs-btn-color:var(--color-neutral-600);--bs-btn-bg:var(--color-neutral-50);--bs-btn-border-color:var(--color-neutral-200);
    --bs-btn-hover-color:var(--color-neutral-900);--bs-btn-hover-bg:var(--color-neutral-100);--bs-btn-hover-border-color:var(--color-neutral-200);
    --bs-btn-active-color:var(--color-neutral-900);--bs-btn-active-bg:var(--color-neutral-100);--bs-btn-active-border-color:var(--color-neutral-350);
    --bs-btn-disabled-color:var(--text-muted);--bs-btn-disabled-bg:var(--color-neutral-50);--bs-btn-disabled-border-color:var(--color-neutral-200)}
.btn-danger,.btn-outline-danger{--bs-btn-color:var(--red-700);--bs-btn-bg:var(--red-50);--bs-btn-border-color:transparent;
    --bs-btn-hover-color:#fff;--bs-btn-hover-bg:var(--red-700);--bs-btn-hover-border-color:transparent;
    --bs-btn-active-color:#fff;--bs-btn-active-bg:var(--red-700);--bs-btn-active-border-color:transparent;
    --bs-btn-disabled-color:var(--red-700);--bs-btn-disabled-bg:var(--red-50);--bs-btn-disabled-border-color:transparent}
.btn-link{--bs-btn-color:var(--color-neutral-900);--bs-btn-hover-color:var(--color-ink-800)}
.btn-check:checked+.btn{background-color:var(--color-neutral-100);color:var(--color-neutral-900);border-color:var(--color-neutral-350)}
.btn-close:focus{box-shadow:none}

/* Forms */
.form-control,.form-select{background-color:var(--color-neutral-50);border:1px solid transparent;border-radius:10px;
    min-height:38px;font-size:14px;color:var(--color-neutral-900)}
.form-control-sm,.form-select-sm{min-height:32px;font-size:13px;border-radius:10px}
textarea.form-control{min-height:auto;height:auto}
.form-control:focus,.form-select:focus{background-color:var(--color-neutral-0);border-color:var(--color-neutral-350);box-shadow:none;color:var(--color-neutral-900)}
.form-control::placeholder{color:var(--text-muted)}
.form-control:disabled,.form-control[readonly],.form-select:disabled{background-color:var(--color-neutral-100)}
.form-control.is-invalid,.form-select.is-invalid{border-color:var(--red-700)}
.invalid-feedback{color:var(--red-700)}
.input-group-text{background:var(--color-neutral-100);border:1px solid transparent;color:var(--color-neutral-600);border-radius:10px;font-size:14px}
.form-label{font-weight:500;font-size:14px;line-height:20px;color:var(--color-neutral-900);margin-bottom:6px}
.form-check-input{width:18px;height:18px;border-radius:5px;-webkit-appearance:none;appearance:none;
    background-color:var(--color-neutral-100);border:1px solid var(--color-neutral-350);margin-top:.1em}
.form-check-input[type=radio]{border-radius:50%}
.form-check-input:checked{background-color:var(--color-ink-900);border-color:var(--color-ink-900)}
.form-check-input:focus{box-shadow:none;border-color:var(--text-muted)}
.form-switch .form-check-input{width:2em;border-radius:2em}

/* Surfaces */
.card,.form-card{background:var(--color-neutral-0);border:1px solid var(--color-neutral-200);border-radius:14px;box-shadow:none}
.form-card{padding:20px}
.card{--bs-card-spacer-x:20px;--bs-card-spacer-y:20px;--bs-card-cap-bg:transparent;--bs-card-border-color:var(--color-neutral-200);--bs-card-border-radius:14px}
.shadow,.shadow-sm{box-shadow:none!important}
.stat-card{background:var(--color-neutral-0);border:1px solid var(--color-neutral-200);border-radius:14px;padding:20px;box-shadow:none}

/* Tables */
.table{--bs-table-color:var(--color-neutral-600);--bs-table-bg:transparent;--bs-table-border-color:var(--color-neutral-200);
    --bs-table-striped-bg:var(--color-neutral-50);--bs-table-hover-bg:var(--color-neutral-50);--bs-table-hover-color:var(--color-neutral-900);
    --bs-table-active-bg:var(--color-neutral-50);color:var(--color-neutral-600)}
.table-light{--bs-table-bg:transparent;--bs-table-color:var(--color-neutral-600);--bs-table-border-color:var(--color-neutral-200)}
.table-custom thead th{font-weight:400;font-size:14px;line-height:20px;letter-spacing:-0.1px;color:var(--color-neutral-600);
    text-transform:none;background:transparent;border-bottom:1px solid var(--color-neutral-200);height:33px;padding-top:6px;padding-bottom:6px;white-space:nowrap}
.table-custom tbody td{vertical-align:middle;border-bottom:1px solid var(--color-neutral-200);height:61.5px}
.table-custom.table-sm tbody td{height:44px}
.table-custom tbody tr:hover>*{background-color:var(--color-neutral-50)}
.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}

/* Badges */
.badge{display:inline-flex;align-items:center;gap:4px;height:23px;padding:0 8px;border-radius:7px;
    background:var(--color-neutral-0);border:1px solid var(--color-neutral-350);color:var(--color-neutral-600);
    font-weight:500;font-size:12px;line-height:16px;letter-spacing:0.5px;text-transform:uppercase;white-space:nowrap}
.badge.bg-success,.badge.bg-success-subtle,.badge.text-bg-success{background:var(--green-50)!important;color:var(--green-700)!important;border-color:transparent}
.badge.bg-danger,.badge.bg-danger-subtle,.badge.text-bg-danger{background:var(--red-50)!important;color:var(--red-700)!important;border-color:transparent}
.badge.bg-warning,.badge.bg-warning-subtle,.badge.text-bg-warning{background:var(--amber-50)!important;color:var(--amber-700)!important;border-color:transparent}
.badge.bg-primary,.badge.bg-info,.badge.bg-secondary,.badge.bg-dark,.badge.bg-light,
.badge.bg-primary-subtle,.badge.bg-info-subtle,.badge.bg-secondary-subtle,
.badge.text-bg-primary,.badge.text-bg-info,.badge.text-bg-secondary,.badge.text-bg-light{background:var(--color-neutral-0)!important;color:var(--color-neutral-600)!important;border-color:var(--color-neutral-350)}

/* Progress */
.progress{--bs-progress-height:6px;--bs-progress-bg:var(--color-neutral-100);--bs-progress-border-radius:999px;
    --bs-progress-bar-bg:var(--color-ink-900);height:6px!important;border-radius:999px;overflow:hidden;background:var(--color-neutral-100)}

/* Alerts */
.alert{--bs-alert-border-radius:10px;border:0;border-radius:10px;font-size:14px}
.alert-success{background:var(--green-50);color:var(--green-700)}
.alert-danger{background:var(--red-50);color:var(--red-700)}
.alert-warning{background:var(--amber-50);color:var(--amber-700)}
.alert-info,.alert-primary,.alert-secondary,.alert-light{background:var(--color-neutral-50);color:var(--color-neutral-700)}
.alert .alert-link{color:inherit}

/* Tabs */
.nav-tabs{--bs-nav-tabs-border-width:0;border-bottom:1px solid var(--color-neutral-200);flex-wrap:nowrap;overflow-x:auto;
    white-space:nowrap;scrollbar-width:none;gap:20px}
.nav-tabs::-webkit-scrollbar{display:none}
.nav-tabs .nav-item{min-width:0}
.nav-tabs .nav-link{border:0;border-bottom:2px solid transparent;border-radius:0;padding:8px 0;margin-bottom:-1px;background:none;
    font-weight:500;font-size:14px;line-height:20px;letter-spacing:-0.15px;color:var(--color-neutral-600);
    transition:background-color 120ms ease-out,color 120ms ease-out}
.nav-tabs .nav-link:hover{color:var(--color-neutral-900);border-color:transparent}
.nav-tabs .nav-link.active,.nav-tabs .nav-item.show .nav-link{color:var(--color-neutral-900);background:none;border-bottom-color:var(--color-ink-900)}
.nav-pills{--bs-nav-pills-border-radius:10px;--bs-nav-pills-link-active-bg:var(--color-ink-900)}
.nav-pills .nav-link{color:var(--color-neutral-600)}

/* Overlays & misc */
.modal-content{border:1px solid var(--color-neutral-200);border-radius:14px;box-shadow:var(--shadow-floating)}
.modal-header,.modal-footer{border-color:var(--color-neutral-200)}
.dropdown-menu{border:1px solid var(--color-neutral-200);border-radius:10px;box-shadow:var(--shadow-floating)}
.list-group{--bs-list-group-border-color:var(--color-neutral-200);--bs-list-group-border-radius:14px;
    --bs-list-group-active-bg:var(--color-ink-900);--bs-list-group-active-border-color:var(--color-ink-900)}
.pagination{--bs-pagination-color:var(--color-neutral-600);--bs-pagination-border-color:var(--color-neutral-200);
    --bs-pagination-hover-bg:var(--color-neutral-50);--bs-pagination-hover-color:var(--color-neutral-900);
    --bs-pagination-active-bg:var(--color-ink-900);--bs-pagination-active-border-color:var(--color-ink-900);
    --bs-pagination-focus-box-shadow:none;--bs-pagination-border-radius:10px}
.breadcrumb{--bs-breadcrumb-divider-color:var(--text-muted);--bs-breadcrumb-item-active-color:var(--text-muted)}
hr{border-color:var(--color-neutral-200);opacity:1}
a{text-decoration-color:var(--color-neutral-350)}
[data-bs-theme=dark]{
    color-scheme:dark;--color-neutral-0:#16191F;--color-neutral-50:#0F1115;--color-neutral-100:#1D2027;
    --color-neutral-200:#2A2E37;--color-neutral-350:#454B57;--color-neutral-400:#A3A9B2;
    --color-neutral-600:#C5CAD2;--color-neutral-700:#D7DBE1;--color-neutral-850:#E6E8EB;
    --color-neutral-900:#E6E8EB;--color-neutral-1000:#F4F5F7;--color-ink-900:#E6E8EB;--color-ink-800:#CCD1D9;
    --text-muted:#A3A9B2;--on-solid:#0F1115;
    --red-50:#351C20;--red-700:#FFA69E;--amber-50:#322819;--amber-700:#F5C46B;
    --green-50:#162E23;--green-700:#82DAA3;--lime-50:#243019;--lime-700:#B6DC8B;
    --bs-body-bg:var(--color-neutral-0);--bs-body-color:var(--color-neutral-600);
    --bs-emphasis-color:var(--color-neutral-900);--bs-emphasis-color-rgb:230,232,235;
    --bs-primary-rgb:230,232,235;--bs-dark-rgb:230,232,235;--bs-success-rgb:130,218,163;
    --bs-danger-rgb:255,166,158;--bs-warning-rgb:245,196,107;--bs-info-rgb:197,202,210;--bs-secondary-rgb:197,202,210;
    --bs-link-color-rgb:230,232,235;--bs-link-hover-color-rgb:204,209,217;
    --bs-secondary-color:var(--text-muted);--bs-tertiary-color:var(--text-muted);
}
.dyn-text{color:color-mix(in srgb,var(--c) 45%,#000)!important}
[data-bs-theme=dark] .dyn-text{color:color-mix(in srgb,var(--c) 45%,#fff)!important}
.btn-primary,.btn-success,.btn-dark{--bs-btn-color:var(--on-solid);--bs-btn-hover-color:var(--on-solid);--bs-btn-active-color:var(--on-solid);--bs-btn-disabled-color:var(--on-solid)}
.btn-danger,.btn-outline-danger{--bs-btn-hover-color:var(--on-solid);--bs-btn-active-color:var(--on-solid)}
.nav-pills{--bs-nav-pills-link-active-color:var(--on-solid)}
.pagination{--bs-pagination-active-color:var(--on-solid)}
.list-group{--bs-list-group-active-color:var(--on-solid)}
:focus-visible{outline-color:var(--color-neutral-900)}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:0;border-radius:10px;background:transparent;color:var(--color-neutral-600);flex-shrink:0}
.icon-btn:hover{background:var(--color-neutral-100);color:var(--color-neutral-900)}
.theme-sun,[data-bs-theme=dark] .theme-moon{display:none}
[data-bs-theme=dark] .theme-sun{display:block}
@media print{
    :root,[data-bs-theme=dark]{color-scheme:light;--color-neutral-0:#fff;--color-neutral-50:#F5F7F9;--color-neutral-100:#EFF1F3;--color-neutral-200:#E2E4E6;--color-neutral-350:#D2D5D9;--color-neutral-600:#4E525A;--color-neutral-700:#3B3F4B;--color-neutral-900:#0D0F14;--color-ink-900:#17191F;--color-ink-800:#262A32;--text-muted:#61666E;--on-solid:#fff;--red-50:#FDE8E5;--red-700:#B42318;--amber-50:#FBF0DA;--amber-700:#8F5B06;--green-50:#E2F4E6;--green-700:#147A3A}
    .theme-toggle{display:none!important}
    [data-bs-theme=dark] .dyn-text{color:color-mix(in srgb,var(--c) 45%,#000)!important}
}
</style>
