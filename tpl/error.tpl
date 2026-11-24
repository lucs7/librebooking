{include file='globalheader.tpl'}
<div class="error text-center">
    <h3>{translate key=$ErrorMessage}</h3>
    {if $ErrorReference}
        <p class="text-muted">{translate key='ErrorReference'}: <code>{$ErrorReference|escape}</code></p>
    {/if}
    <h5>
        <a class="link-primary"
            href="//{$smarty.server.HTTP_HOST|escape}{$smarty.server.REQUEST_URI|escape}">{translate key='ReturnToPreviousPage'}</a>
    </h5>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}