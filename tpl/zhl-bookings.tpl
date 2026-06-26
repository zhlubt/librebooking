{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
      <div class="ph-text">
        <h1>Meine Buchungen</h1>
        <div class="ph-sub">Ihre Reservierungen, Abholungen und Rückgaben auf einen Blick.</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="{$Path}zhl-dashboard.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <span>Neu buchen</span>
        </a>
      </div>
    </div>

    {if isset($CancelledNotice) && $CancelledNotice}
      <div class="info-box" style="border-color:#bfe3d2;background:#eaf6ef;color:#00744c">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
        <span>Ihre Ausleihe wurde storniert.{if isset($CancelWarning) && $CancelWarning != ''} <b>{$CancelWarning|escape}</b>{/if}</span>
      </div>
    {/if}

    {if !$HasAny}
      <div class="info-box">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
        <span>Sie haben aktuell keine Buchungen. Über <b>Neu buchen</b> reservieren Sie Geräte und Bundles.</span>
      </div>
    {else}

    <div class="seg" role="tablist" id="zhlBookingTabs">
      <button type="button"{if $DefaultGroup == 'current'} class="active"{/if} data-group="current">Aktuell <span class="cnt">{$CurrentCount}</span></button>
      <button type="button"{if $DefaultGroup == 'upcoming'} class="active"{/if} data-group="upcoming">Anstehend <span class="cnt">{$UpcomingCount}</span></button>
      <button type="button"{if $DefaultGroup == 'past'} class="active"{/if} data-group="past">Vergangen <span class="cnt">{$PastCount}</span></button>
    </div>

    {foreach from=['current'=>$Current, 'upcoming'=>$Upcoming, 'past'=>$Past] key=groupKey item=rows}
      <div class="zhl-bk-group" data-group="{$groupKey}"{if $groupKey != $DefaultGroup} style="display:none"{/if}>
        {if $rows|@count == 0}
          <div class="info-box">
            <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
            <span>Keine Einträge in dieser Ansicht.</span>
          </div>
        {else}
          {foreach from=$rows item=bk}
            <div class="booking">
              <span class="b-ic">
                {if $bk.kind == 'bundle'}
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                {else}
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 9l5-3v12l-5-3"/></svg>
                {/if}
              </span>
              <div class="b-main">
                <div class="b-title">{$bk.title|escape}</div>
                <div class="b-meta">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>{$bk.range|escape}</span>
                  <span>{$bk.items|escape}</span>
                  {if $bk.pickup != ''}<span>{$bk.pickup|escape}</span>{/if}
                </div>
              </div>
              <div class="b-right">
                <span class="badge badge-{$bk.state|escape}">{$bk.label|escape}</span>
                <a class="b-link" href="{$Path}zhl-booking-detail.php?id={$bk.ref|escape:'url'}">
                  <span>Details</span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </div>
            </div>
          {/foreach}
        {/if}
      </div>
    {/foreach}

    <div class="info-box" style="margin-top:18px">
      <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
      <span>Eine Buchung stornieren oder ändern? Öffnen Sie die Buchung und nutzen Sie <b>Stornieren</b>, oder melden Sie sich spätestens einen Tag vor Abholung bei uns.</span>
    </div>

    <script>
    (function () {
      var tabs = document.getElementById('zhlBookingTabs');
      if (!tabs) { return; }
      var groups = document.querySelectorAll('.zhl-bk-group');
      tabs.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-group]');
        if (!btn) { return; }
        tabs.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var want = btn.getAttribute('data-group');
        groups.forEach(function (g) {
          g.style.display = (g.getAttribute('data-group') === want) ? '' : 'none';
        });
      });
    })();
    </script>

    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
