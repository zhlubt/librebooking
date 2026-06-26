{include file='globalheader.tpl'}
<div class="error text-center" style="max-width:680px;margin:40px auto;padding:0 16px;">
    <h3>{translate key=$ErrorMessage}</h3>
    <p class="text-muted" style="margin-top:10px;">
        Der Fehler wurde protokolliert. Falls er erneut auftritt, melden Sie ihn bitte unter Angabe des
        unten stehenden Zeitstempels.
    </p>
    {if isset($ErrorReference) && $ErrorReference != ''}
        <p class="text-muted" style="font-size:.9em;">Referenz: <code>{$ErrorReference|escape}</code></p>
    {/if}
    {if isset($ErrorDetail) && $ErrorDetail != ''}
        <pre style="text-align:left;white-space:pre-wrap;word-break:break-word;background:#f6f8fa;border:1px solid #e1e4e8;border-radius:8px;padding:12px 14px;margin:16px 0;font-size:.85em;color:#b31d28;">{$ErrorDetail|escape}</pre>
        <p class="text-muted" style="font-size:.8em;">Diese technischen Details sind nur für Administratoren bzw. im Debug-Modus sichtbar.</p>
    {/if}
    <h5 style="margin-top:18px;">
        <a class="link-primary"
            href="//{$smarty.server.HTTP_HOST}{$smarty.server.REQUEST_URI}">{translate key='ReturnToPreviousPage'}</a>
    </h5>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
