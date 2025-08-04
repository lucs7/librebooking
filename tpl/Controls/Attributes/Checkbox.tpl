<div class="form-group {$class}">
    {if $readonly}
        <label class="customAttribute readonly" fw-bold for="{$attributeId}">{$attribute->Label()}</label>
        <span class="attributeValue {$class}">{if $attribute->Value() == "1"}{translate key='True'}{else}{translate key='False'}{/if}</span>
    {elseif $searchmode}
        <label class="customAttribute search fw-bold" for="{$attributeId}">{$attribute->Label()}
            {if $attribute->Description()}
                <i class="bi bi-question-circle-fill link-primary ms-1" data-bs-toggle="tooltip" data-bs-title="{$attribute->Description()|escape:'html'}"></i>
            {/if}
        </label>
        <select id="{$attributeId}" name="{$attributeName}" class="customAttribute form-select {$inputClass}">
            <option value="">--</option>
            <option value="0" {if $attribute->Value() == "0"}selected="selected" {/if}>{translate key=No}</option>
            <option value="1" {if $attribute->Value() == "1"}selected="selected" {/if}>{translate key=Yes}</option>
        </select>
    {else}
        <div class="form-check">
            <input type="checkbox" value="1" id="{$attributeId}" name="{$attributeName}" {if $attribute->Value() == "1"}checked="checked" {/if} class="{$inputClass} form-check-input" />
            <label class="customAttribute standard form-check-label fw-bold" for="{$attributeId}">{$attribute->Label()}
                {if $attribute->Required() && !$searchmode}
                    <i class="bi bi-asterisk"></i>
                {/if}
                {if $attribute->Description()}
                    <i class="bi bi-question-circle-fill link-primary ms-1" data-bs-toggle="tooltip" data-bs-title="{$attribute->Description()|escape:'html'}"></i>
                {/if}
            </label>
        </div>
    {/if}
</div>