{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="crumb">
      <a href="{$Path}zhl-bookings.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> <span>Meine Buchungen</span></a>
    </div>

    <div class="page-head">
      <span class="ph-icon">
        {if $IsBundle}
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        {else}
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 9l5-3v12l-5-3"/></svg>
        {/if}
      </span>
      <div class="ph-text">
        <h1>{$Title|escape}</h1>
        <div class="ph-sub">Ausleihe {$Ref|escape} · {if $IsBundle}Bundle aus {$DeviceCount} Geräten{else}Einzelgerät{/if}</div>
      </div>
    </div>

    {if isset($FlashError) && $FlashError != ''}
      <div class="info-box" style="margin:0 0 18px;border-color:#f0c2c2;background:#fdeeee;color:#9a2828">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
        <span>{$FlashError|escape}</span>
      </div>
    {/if}

    <div class="grid-2">
      <!-- Details -->
      <div class="card">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
          <h2>Details</h2>
        </div>
        <div class="card-body">
          <dl class="dl">
            <div><dt>Status</dt><dd><span class="badge badge-{$State|escape}">{$StatusLabel|escape}</span></dd></div>
            <div><dt>Zeitraum</dt><dd>{$PeriodLabel|escape}</dd></div>
            <div><dt>Art</dt><dd>{if $IsBundle}Bundle ({$DeviceCount} Geräte){else}Einzelgerät{/if}</dd></div>
            <div{if $CreatedLabel == ''} class="last"{/if}><dt>Buchungsnummer</dt><dd>{$Ref|escape}</dd></div>
            {if $CreatedLabel != ''}<div class="last"><dt>Gebucht am</dt><dd>{$CreatedLabel|escape}</dd></div>{/if}
          </dl>
          {if $Description != ''}
            <div class="hint" style="margin-top:14px">{$Description|escape}</div>
          {/if}
        </div>
      </div>

      <!-- Abholung & Rückgabe -->
      <div class="card">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
          <h2>Abholung &amp; Rückgabe</h2>
        </div>
        <div class="card-body">
          {if $HasHandover}
            <dl class="dl">
              {if $EinfApptLabel != ''}<div><dt>Einführung in die Geräte</dt><dd>{$EinfApptLabel|escape}</dd></div>{/if}
              {if $PickupLabel != ''}<div><dt>Abholung</dt><dd>{$PickupLabel|escape}</dd></div>{/if}
              <div class="last"><dt>Rückgabe{if $ReturnLabel == ''} bis{/if}</dt><dd>{if $ReturnLabel != ''}{$ReturnLabel|escape}{else}Ende des Buchungszeitraums{/if}</dd></div>
            </dl>
          {else}
            <p class="hint">Für diese Buchung sind keine gesonderten Abhol- oder Rückgabetermine hinterlegt. Geräte werden zum Buchungszeitraum bereitgestellt.</p>
          {/if}
          <div class="info-box" style="margin-top:16px">
            <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
            <span>Bitte bringen Sie zur Abholung Ihren Uni-Ausweis mit.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enthaltene Geräte -->
    {if $DeviceCount > 0}
    <div class="card">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
        <h2>Enthaltene Geräte</h2>
        <span class="ch-right muted">{$DeviceCount}</span>
      </div>
      <table class="ztable">
        <thead><tr><th>Gerät</th><th>Einführung</th></tr></thead>
        <tbody>
        {foreach from=$Devices item=d}
          <tr>
            <td style="color:var(--text);font-weight:500">{$d.name|escape}</td>
            <td>{if isset($d.einfLabel) && $d.einfLabel != ''}<span class="badge badge-{$d.einfState|escape}">{$d.einfLabel|escape}</span>{else}<span class="muted">—</span>{/if}</td>
          </tr>
        {/foreach}
        </tbody>
      </table>
      <div class="info-box" style="margin:16px 24px 6px">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
        <span>Ist für ein Gerät eine Einführung <b>erforderlich</b>, buchen Sie diese beim Reservieren mit. Nach absolvierter Einführung gilt sie dauerhaft — Sie sehen Ihre Zertifikate unter <a href="{$Path}zhl-account.php">Mein Konto</a>.</span>
      </div>
    </div>
    {/if}

    <!-- Stornieren -->
    {if isset($CanCancel) && $CanCancel}
    <div class="card" style="margin-top:18px">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></span>
        <h2>Ausleihe stornieren</h2>
      </div>
      <div class="card-body">
        <p class="hint">Solange die Abholung noch nicht begonnen hat, können Sie diese Buchung stornieren. Reservierung, Abhol- und (falls vorhanden) Einführungstermin werden dabei automatisch wieder freigegeben.</p>
        <form method="post" action="{$Path}zhl-booking-detail.php?id={$Ref|escape:'url'}"
              onsubmit="return confirm('Diese Ausleihe wirklich stornieren? Reservierung, Abhol- und Einführungstermin werden abgesagt. Das kann nicht rückgängig gemacht werden.');"
              style="margin-top:14px">
          {csrf_token}
          <input type="hidden" name="id" value="{$Ref|escape}">
          <button type="submit" class="btn" style="background:#c0392b;color:#fff;border:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            <span>Ausleihe stornieren</span>
          </button>
        </form>
      </div>
    </div>
    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
