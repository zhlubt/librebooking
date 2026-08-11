{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/></svg></span>
      <div class="ph-text">
        <h1>{$Name|escape}</h1>
        <div class="ph-sub">Geräteakte — Stammdaten, aktuelle Ausleihe und Historie.</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="{$Path}zhl-medienmanager-ausleihen.php">Ausleihen</a>
        <a class="btn btn-light" href="{$Path}zhl-medienmanager.php">Rückgaben</a>
        <a class="btn btn-light" href="{$Path}zhl-dashboard.php">Dashboard</a>
      </div>
    </div>

    {if $EndedFlash}
      <div class="info-box" style="border-color:#bfe3d2">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 10 10.5 15.5 8 13"/></svg></span>
        <span>Ausleihe beendet — das Gerät ist wieder buchbar.</span>
      </div>
    {/if}
    {if $EndedError}
      <div class="info-box" style="border-color:var(--err-bd)">
        <span>Die Ausleihe konnte nicht beendet werden. Bitte erneut versuchen.</span>
      </div>
    {/if}

    <div class="card" style="margin-bottom:18px">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
        <h2>Stammdaten</h2>
        <span class="ch-right"><span class="badge {$StatusBadge|escape}">{$StatusLabel|escape}</span></span>
      </div>
      <div class="card-body" style="padding:0">
        <table class="ztable">
          <tbody>
            <tr><td class="muted" style="width:220px">Geräte-Typ</td><td>{if $TypeLabel != ''}{$TypeLabel|escape}{else}<span class="muted">—</span>{/if}</td></tr>
            <tr><td class="muted">Schedule</td><td>{if $ScheduleName != ''}{$ScheduleName|escape}{else}<span class="muted">—</span>{/if}</td></tr>
            {if $Standort != ''}<tr><td class="muted">Standort</td><td>{$Standort|escape}</td></tr>{/if}
            <tr><td class="muted">Abholung</td><td>{$AbholungLabel|escape}{if $Abholort != ''} <span class="muted">· {$Abholort|escape}</span>{/if}</td></tr>
            <tr><td class="muted">Rückgabe</td><td>{$RueckgabeLabel|escape}{if $Rueckgabeort != ''} <span class="muted">· {$Rueckgabeort|escape}</span>{/if}</td></tr>
            <tr><td class="muted">Einführung</td><td>{$EinfuehrungLabel|escape}{if $EinfuehrungTyp != ''} <span class="muted">· {$EinfuehrungTyp|escape}</span>{/if}</td></tr>
            <tr><td class="muted">Zertifikatspflicht</td><td>{if $CertLabel != ''}{$CertLabel|escape}{else}<span class="muted">keine</span>{/if}</td></tr>
            <tr><td class="muted">In Bundles</td><td>{if $BundleLabel != ''}{$BundleLabel|escape}{else}<span class="muted">keine</span>{/if}</td></tr>
            {if $Beschreibung != ''}<tr><td class="muted">Beschreibung</td><td>{$Beschreibung|sanitize_rich_text}</td></tr>{/if}
            {if $Notizen != ''}<tr><td class="muted">Notizen (intern)</td><td>{$Notizen|sanitize_rich_text}</td></tr>{/if}
          </tbody>
        </table>
      </div>
    </div>

    <div class="card" style="margin-bottom:18px">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
        <h2>Aktuelle Ausleihe</h2>
      </div>
      <div class="card-body" style="padding:0">
        {if $Current}
          <table class="ztable">
            <thead><tr><th>Zeitraum</th><th>Ausleihende:r</th><th>Vorhaben</th><th>Status</th></tr></thead>
            <tbody>
              <tr>
                <td style="font-size:13px">{$Current.startLabel|escape} – {$Current.endLabel|escape} Uhr</td>
                <td>{$Current.borrower|escape}{if $Current.email != ''}<div class="muted" style="font-size:13px"><a href="mailto:{$Current.email|escape}" style="color:inherit">{$Current.email|escape}</a></div>{/if}</td>
                <td>
                  <a href="{$Path}zhl-buchung-admin.php?ref={$Current.ref|escape:'url'}" style="color:inherit">{if $Current.title != ''}<strong>{$Current.title|escape}</strong>{else}Buchung ansehen{/if}</a>
                  {if $Current.deviceCount > 1}<div style="font-size:13px"><span class="badge badge-info">Bundle · {$Current.deviceCount} Geräte</span></div>{/if}
                </td>
                <td>
                  <span class="badge {$Current.badgeClass|escape}">{$Current.stateLabel|escape}</span>
                  <form method="post" style="margin-top:8px" onsubmit="return confirm('Diese Ausleihe jetzt als zurückgegeben markieren und beenden? Das Gerät wird sofort wieder buchbar.');">
                    {csrf_token}
                    <input type="hidden" name="ref" value="{$Current.ref|escape}">
                    <button type="submit" class="btn btn-outline btn-sm">Ausleihe beenden</button>
                  </form>
                </td>
              </tr>
            </tbody>
          </table>
        {else}
          <div class="info-box" style="margin:14px">
            <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 10 10.5 15.5 8 13"/></svg></span>
            <span>Derzeit nicht ausgeliehen.</span>
          </div>
        {/if}
      </div>
    </div>

    <div class="card" style="margin-bottom:18px">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
        <h2>Ausleih-Historie &amp; geplant</h2>
        <span class="ch-right muted">{$History|@count}</span>
      </div>
      <div class="card-body" style="padding:0">
        {if $History|@count > 0}
          <table class="ztable">
            <thead><tr><th>Zeitraum</th><th>Ausleihende:r</th><th>Vorhaben</th><th>Status</th></tr></thead>
            <tbody>
            {foreach from=$History item=row}
              <tr>
                <td style="font-size:13px">{$row.startLabel|escape} – {$row.endLabel|escape} Uhr</td>
                <td>{$row.borrower|escape}{if $row.email != ''}<div class="muted" style="font-size:13px"><a href="mailto:{$row.email|escape}" style="color:inherit">{$row.email|escape}</a></div>{/if}</td>
                <td>
                  <a href="{$Path}zhl-buchung-admin.php?ref={$row.ref|escape:'url'}" style="color:inherit">{if $row.title != ''}<strong>{$row.title|escape}</strong>{else}Buchung ansehen{/if}</a>
                  {if $row.deviceCount > 1}<div style="font-size:13px"><span class="badge badge-info">Bundle · {$row.deviceCount} Geräte</span></div>{/if}
                </td>
                <td>
                  <span class="badge {$row.badgeClass|escape}">{$row.stateLabel|escape}</span>
                  {if $row.returnedLabel != '' && $row.stateLabel == 'zurückgegeben'}<div class="muted" style="font-size:13px">am {$row.returnedLabel|escape} Uhr</div>{/if}
                </td>
              </tr>
            {/foreach}
            </tbody>
          </table>
          {if $HistoryLimited}
            <div class="muted" style="padding:10px 14px;font-size:13px">Es werden nur die letzten 100 Reservierungen angezeigt.</div>
          {/if}
        {else}
          <div class="info-box" style="margin:14px">
            <span>Für dieses Gerät gibt es noch keine Reservierungen.</span>
          </div>
        {/if}
      </div>
    </div>

    {if $Cancelled|@count > 0}
      <div class="card" style="margin-bottom:18px">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
          <h2>Stornierte Buchungen</h2>
          <span class="ch-right muted">{$Cancelled|@count}</span>
        </div>
        <div class="card-body" style="padding:0">
          <table class="ztable">
            <thead><tr><th>Zeitraum</th><th>Ausleihende:r</th><th>Vorhaben</th><th>Storniert am</th></tr></thead>
            <tbody>
            {foreach from=$Cancelled item=row}
              <tr>
                <td style="font-size:13px">{if $row.startLabel != ''}{$row.startLabel|escape} – {$row.endLabel|escape} Uhr{else}<span class="muted">—</span>{/if}</td>
                <td>{if $row.borrower != ''}{$row.borrower|escape}{else}<span class="muted">unbekannt</span>{/if}</td>
                <td><a href="{$Path}zhl-buchung-admin.php?ref={$row.ref|escape:'url'}" style="color:inherit">{if $row.title != ''}{$row.title|escape}{else}Buchung ansehen{/if}</a></td>
                <td style="font-size:13px">{$row.cancelledLabel|escape} Uhr</td>
              </tr>
            {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
