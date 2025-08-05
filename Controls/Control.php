<?php

abstract class Control
{
    /**
     * @var SmartyPage|Smarty\Smarty
     */
    protected $smarty;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var Smarty\Data
     */
    protected $data;

    public function __construct(SmartyPage $smarty)
    {
        $this->smarty = $smarty;
        $this->id = uniqid();

        $this->data = $smarty->createData();
    }

    public function Set($var, $value)
    {
        $this->data->assign($var, $value);
    }

    protected function Get($var)
    {
        return $this->data->getTemplateVars($var);
    }

    protected function Display($templateName)
    {
        $tpl = $this->smarty->createTemplate($templateName, $this->data);
        $tpl->display();
    }

    abstract public function PageLoad();
}
