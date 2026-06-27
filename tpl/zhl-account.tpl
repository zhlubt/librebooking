{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
      <div class="ph-text">
        <h1>{translate key="ZhlAccountTitle"}</h1>
        <div class="ph-sub">{translate key="ZhlAccountSub"}</div>
      </div>
    </div>

    <!-- Persönliche Daten (read-only Übersicht) -->
    <div class="card">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <h2>{translate key="ZhlAccountPersonalData"}</h2>
      </div>
      <div class="card-body">
        <dl class="dl">
          <div><dt>{translate key="ZhlAccountFirstName"}</dt><dd>{if $FirstName != ''}{$FirstName|escape}{else}<span class="muted">—</span>{/if}</dd></div>
          <div><dt>{translate key="ZhlAccountLastName"}</dt><dd>{if $LastName != ''}{$LastName|escape}{else}<span class="muted">—</span>{/if}</dd></div>
          <div>
            <dt>{translate key="ZhlAccountEmailLabel"}</dt>
            <dd>{$Email|escape}<div class="hint">{translate key="ZhlAccountEmailHint"}</div></dd>
          </div>
          <div><dt>{translate key="ZhlAccountPhone"}</dt><dd>{if $Phone != ''}{$Phone|escape}{else}<span class="muted">{translate key="ZhlNotProvided"}</span>{/if}</dd></div>
          <div class="last"><dt>{translate key="ZhlAccountOrganization"}</dt><dd>{if $Organization != ''}{$Organization|escape}{else}<span class="muted">{translate key="ZhlNotProvided"}</span>{/if}</dd></div>
        </dl>
        <div class="form-actions">
          <a class="btn btn-primary" href="{$Path}profile.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
            <span>{translate key="ZhlAccountEditData"}</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Zertifikate / absolvierte Einführungen -->
    <div class="card">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg></span>
        <h2>{translate key="ZhlAccountMyCertificates"}</h2>
        {if $IsAdmin}
          <a class="btn btn-light" style="margin-left:auto" href="{$Path}zhl-certificates-admin.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>{translate key="ZhlManage"}</span>
          </a>
        {/if}
      </div>
      <div class="card-body">
        <p class="hint" style="margin-bottom:14px">{translate key="ZhlAccountCertHint"}</p>
        {if $HasCertificates}
          <div class="zhl-cert-list">
            {foreach from=$Certificates item=cert}
              <div class="zhl-cert{if $cert.expired} zhl-cert-expired{/if}">
                <span class="zc-ic">
                  {if $cert.expired}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  {else}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  {/if}
                </span>
                <div class="zc-main">
                  <div class="zc-title">{$cert.name|escape}</div>
                  {if $cert.devices|@count > 0}
                    <div class="zc-devices">{foreach from=$cert.devices item=dev name=dl}{$dev|escape}{if !$smarty.foreach.dl.last} · {/if}{/foreach}</div>
                  {/if}
                  <div class="zc-meta">
                    {if $cert.grantedLabel != ''}<span>{translate key="ZhlAccountCertGranted"} {$cert.grantedLabel|escape}</span>{/if}
                    {if $cert.expiryLabel != ''}<span>{if $cert.expired}{translate key="ZhlAccountCertExpiredOn"}{else}{translate key="ZhlAccountCertValidUntil"}{/if} {$cert.expiryLabel|escape}</span>{else}<span>{translate key="ZhlAccountCertUnlimited"}</span>{/if}
                  </div>
                  {if !$cert.expired && $cert.confidential.has}
                    <div class="zc-conf">
                      <div class="zc-conf-head"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Vertrauliche Infos <span class="zc-conf-note">— nur für Sie sichtbar</span></div>
                      {if $cert.confidential.code != ''}
                        <div class="zc-conf-row">
                          <span class="zc-conf-label">Transponder-Tresor-Code</span>
                          <code class="zc-code" data-code="{$cert.confidential.code|escape}">••••••••</code>
                          <button type="button" class="zc-btn zc-reveal">anzeigen</button>
                          <button type="button" class="zc-btn zc-copy">kopieren</button>
                        </div>
                      {/if}
                      {if $cert.confidential.news != ''}
                        <div class="zc-conf-news">{$cert.confidential.news|escape|nl2br}</div>
                      {/if}
                      {if $cert.confidential.docUrl != ''}
                        <a class="zc-conf-doc" href="{$cert.confidential.docUrl|escape}" target="_blank" rel="noopener">Dokument öffnen →</a>
                      {/if}
                    </div>
                  {/if}
                </div>
                <span class="badge {if $cert.expired}badge-muted{else}badge-ok{/if}">{if $cert.expired}{translate key="ZhlAccountCertBadgeExpired"}{else}{translate key="ZhlAccountCertBadgeActive"}{/if}</span>
              </div>
            {/foreach}
          </div>
        {else}
          <div class="info-box">
            <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
            <span>{translate key="ZhlAccountNoCerts"}</span>
          </div>
        {/if}
      </div>
    </div>

    <div class="grid-2">
      <!-- Passwort -->
      <div class="card">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <h2>{translate key="ZhlAccountPassword"}</h2>
        </div>
        <div class="card-body">
          <p class="hint" style="margin-bottom:14px">{translate key="ZhlAccountPasswordHint"}</p>
          <div class="form-actions">
            <a class="btn btn-primary" href="{$Path}password.php"><span>{translate key="ZhlAccountChangePassword"}</span></a>
          </div>
        </div>
      </div>

      <!-- Hinweise -->
      <div class="card">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
          <h2>{translate key="ZhlAccountSettings"}</h2>
        </div>
        <div class="card-body">
          <p class="hint" style="margin-bottom:14px">{translate key="ZhlAccountSettingsHint"}</p>
          <div class="form-actions">
            <a class="btn btn-light" href="{$Path}profile.php"><span>{translate key="ZhlAccountOpenProfile"}</span></a>
          </div>
        </div>
      </div>
    </div>

    <div class="info-box">
      <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
      <span>{translate key="ZhlAccountSeparateNote"}</span>
    </div>

  </div>
</main>

{literal}
<script>
(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.zc-reveal, .zc-copy') : null;
    if (!btn) { return; }
    var row = btn.closest('.zc-conf-row');
    var codeEl = row ? row.querySelector('.zc-code') : null;
    if (!codeEl) { return; }
    var code = codeEl.getAttribute('data-code') || '';
    if (btn.classList.contains('zc-reveal')) {
      var shown = codeEl.classList.toggle('is-shown');
      codeEl.textContent = shown ? code : '••••••••';
      btn.textContent = shown ? 'verbergen' : 'anzeigen';
    } else {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(function () {
          var t = btn.textContent; btn.textContent = 'kopiert ✓';
          setTimeout(function () { btn.textContent = t; }, 1500);
        });
      }
    }
  });
})();
</script>
{/literal}

{include file='globalfooter.tpl'}
