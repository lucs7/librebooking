<?php

require_once ROOT_DIR.'lib/WebService/namespace.php';
require_once ROOT_DIR.'lib/Application/Attributes/namespace.php';
require_once ROOT_DIR.'WebServices/Responses/CustomAttributes/CustomAttributesResponse.php';

class AttributesWebService
{
    /**
     * @var IRestServer
     */
    private $server;

    /**
     * @var IAttributeService
     */
    private $attributeService;

    public function __construct(IRestServer $server, IAttributeService $attributeService)
    {
        $this->server = $server;
        $this->attributeService = $attributeService;
    }

    /**
     * @name GetCategoryAttributes
     *
     * @description Gets all custom attribute definitions for the requested category
     * Categories are RESERVATION = 1, USER = 2, RESOURCE = 4
     *
     * @response CustomAttributesResponse
     *
     * @param int $categoryId
     *
     * @return void
     */
    public function GetAttributes($categoryId)
    {
        $attributes = $this->attributeService->GetByCategory($categoryId);

        $this->server->WriteResponse(new CustomAttributesResponse($this->server, $attributes));
    }

    /**
     * @name GetAttribute
     *
     * @description Gets all custom attribute definitions for the requested attribute
     *
     * @response CustomAttributeDefinitionResponse
     *
     * @param int $attributeId
     *
     * @return void
     */
    public function GetAttribute($attributeId)
    {
        $attribute = $this->attributeService->GetById($attributeId);

        if (null != $attribute) {
            $this->server->WriteResponse(new CustomAttributeDefinitionResponse($this->server, $attribute));
        } else {
            $this->server->WriteResponse(RestResponse::NotFound(), RestResponse::NOT_FOUND_CODE);
        }
    }
}
