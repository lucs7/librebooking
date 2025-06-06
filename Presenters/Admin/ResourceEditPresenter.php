<?php

require_once(ROOT_DIR . 'Presenters/Admin/ManageResourcesPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/ResourceRepository.php');
require_once(ROOT_DIR . 'Domain/Access/ScheduleRepository.php');
require_once(ROOT_DIR . 'Domain/Access/PageableDataStore.php');

class ResourceEditPresenter extends ManageResourcesPresenter
{
    public function PageLoad()
    {
        $resourceAttributes = $this->attributeService->GetByCategory(CustomAttributeCategory::RESOURCE);
        $resourceId = $this->page->GetResourceId();
        $resource = $this->resourceRepository->LoadById($resourceId);

        if ($resource !== null) {
            $resources = [$resource];
            $pageInfo = new PageInfo(1, 1, 1);
        } else {
            $resources = [];
            $pageInfo = new PageInfo(0, 1, 1);
        }

        $this->page->BindResources($resources);
        $this->page->BindPageInfo($pageInfo);

        $schedules = $this->scheduleRepository->GetAll();
        $scheduleList = [];
        foreach ($schedules as $schedule) {
            $scheduleList[$schedule->GetId()] = $schedule->GetName();
        }
        $this->page->BindSchedules($scheduleList);
        $this->page->AllSchedules($schedules);

        $resourceTypes = $this->resourceRepository->GetResourceTypes();
        $resourceTypeList = [];
        foreach ($resourceTypes as $resourceType) {
            $resourceTypeList[$resourceType->Id()] = $resourceType;
        }
        $this->page->BindResourceTypes($resourceTypeList);

        $statusReasons = $this->resourceRepository->GetStatusReasons();
        $statusReasonList = [];
        foreach ($statusReasons as $reason) {
            $statusReasonList[$reason->Id()] = $reason;
        }
        $this->page->BindResourceStatusReasons($statusReasonList);

        $groups = $this->groupRepository->GetGroupsByRole(RoleLevel::RESOURCE_ADMIN);
        $this->page->BindAdminGroups($groups);

        $attributeList = $this->attributeService->GetByCategory(CustomAttributeCategory::RESOURCE);
        $this->page->BindAttributeList($attributeList);

        $filterValues = $this->page->GetFilterValues();
        $this->InitializeFilter($filterValues, $resourceAttributes);

        $this->page->BindResourceGroups($this->resourceRepository->GetResourceGroups(null, new ResourceFilterNone()));
    }
}
