<?php

require_once(ROOT_DIR . 'Controls/Control.php');

class AttributeControl extends Control
{
    public function __construct(\LibreBooking\Common\Templating\TemplateRenderer|SmartyPage $smarty)
    {
        parent::__construct($smarty);
    }

    public function PageLoad()
    {
        $templates[CustomAttributeTypes::CHECKBOX] = 'checkbox.twig';
        $templates[CustomAttributeTypes::MULTI_LINE_TEXTBOX] = 'multi-line-textbox.twig';
        $templates[CustomAttributeTypes::SELECT_LIST] = 'select-list.twig';
        $templates[CustomAttributeTypes::SINGLE_LINE_TEXTBOX] = 'single-line-textbox.twig';
        $templates[CustomAttributeTypes::DATETIME] = 'date.twig';

        /** @var Attribute|CustomAttribute $attribute */
        $attribute = $this->Get('attribute');

        if (is_a($attribute, 'CustomAttribute')) {
            $attributeVal = $this->Get('value');
            $attribute = new LBAttribute($attribute, $attributeVal);
            $this->Set('attribute', $attribute);
        }

        $prefix = $this->Get('namePrefix');
        $idPrefix = $this->Get('idPrefix');

        $this->Set('attributeName', sprintf('%s%s[%s]', $prefix, FormKeys::ATTRIBUTE_PREFIX, $attribute->Id()));
        $this->Set('attributeId', sprintf('%s%s%s', $idPrefix, FormKeys::ATTRIBUTE_PREFIX, $attribute->Id()));
        $this->Display('components/controls/attributes/' . $templates[$attribute->Type()]);
    }
}
