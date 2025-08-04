<div class="form-group {$class}">
    <label class="customAttribute {if $readonly}readonly{elseif $searchmode}search{else}standard{/if} fw-bold" for="{$attributeId}">{$attribute->Label()}
        {if $attribute->Required() && !$searchmode}
            <i class="bi bi-asterisk text-danger align-top text-small"></i>
        {/if}
        {if $attribute->Description() && !$readonly}
            <i class="bi bi-question-circle-fill link-primary ms-1" data-bs-toggle="tooltip" data-bs-title="{$attribute->Description()|escape:'html'}"></i>
        {/if}
    </label>
    {if $readonly}
        <span class="attributeValue {$class}">{$attribute->Value()|nl2br}</span>
    {else}
        <div class="position-relative">
            <textarea id="{$attributeId}" name="{$attributeName}" rows="2" class="customAttribute form-control w-100 {$inputClass}" {if $attribute->Required() && !$searchmode}required{/if}>{$attribute->Value()}</textarea>
            {if $searchmode}
                <span class="searchclear searchclear-label bi bi-x-circle-fill" ref="{$attributeId}"></span>
            {/if}
        </div>
    {/if}
</div>