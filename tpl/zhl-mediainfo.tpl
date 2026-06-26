{* ZHL D2 — Info-Material zu den gebuchten Medien (Erfolgsseite). Erwartet $MediaInfos:
   Liste von [device, type, url, text]. Zeigt nur Geräte mit hinterlegtem Info-Link. *}
{assign var=zhlHasInfo value=false}
{if $MediaInfos}{foreach from=$MediaInfos item=mi}{if $mi.url != ''}{assign var=zhlHasInfo value=true}{/if}{/foreach}{/if}
{if $zhlHasInfo}
	<div class="zhl-ueb-item ok" style="margin:14px 0; display:block;">
		<strong>ℹ Informieren Sie sich jetzt über die gebuchten Materialien und Medien</strong>
		<span class="zhl-muted zhl-small" data-en="Find out more about the materials and media you booked">Alles Wissenswerte zu Bedienung, Hinweisen und Beschreibungen.</span>
		<ul class="zhl-mediainfo-list" style="margin:8px 0 0; padding-left:0; list-style:none;">
			{foreach from=$MediaInfos item=mi}
				{if $mi.url != ''}
					<li style="margin:6px 0;">
						<a href="{$mi.url|escape}" target="_blank" rel="noopener">{$mi.device|escape} → <span data-en="info material">Info-Material</span></a>
						{if $mi.text != ''}<div class="zhl-muted zhl-small">{$mi.text|escape}</div>{/if}
					</li>
				{/if}
			{/foreach}
		</ul>
	</div>
{/if}
