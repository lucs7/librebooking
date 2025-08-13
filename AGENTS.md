# AGENTS

## Namespace Refactor Strategy

We are modernizing the codebase by introducing PHP namespaces across the project. To keep the effort manageable:

1. **Work folder by folder.** Complete one directory tree (e.g. `Domain`, `Pages`, `Presenters`, etc.) before moving to the next.
2. **Add namespaces and aliases.** Each class should declare its `LibreBooking` namespace that mirrors its directory structure. Preserve backward compatibility with `class_alias` for existing global class names.
3. **Review includes.** When a class is namespaced, check any `require_once` or `include` statements and remove them if Composer autoloading makes them unnecessary.
4. **Qualify dependencies.** Update type hints and references to use fully qualified class names or `use` imports.
5. **Run tests.** After changes in any folder, execute `composer phpunit` to verify that the application still loads.

This document lives at the repository root and applies to the entire project.

## Proposed Namespace Structure

Application code should live under the `LibreBooking` root namespace following the directory layout below:

- `Domain` &rarr; `LibreBooking\Domain`
- `Pages` &rarr; `LibreBooking\Pages`
- `Presenters` &rarr; `LibreBooking\Presenters`
- `Controls` &rarr; `LibreBooking\Controls`
- `WebServices` &rarr; `LibreBooking\WebServices`
- `lib/Common` &rarr; `LibreBooking\Common`
- Additional directories should adopt a similar `LibreBooking\<Folder>` namespace as they are refactored.

## Outstanding Files Without Namespaces

The following PHP files still lack a `LibreBooking` namespace declaration and should be refactored.

### Domain

- Domain/ReservationTypes.php
- Domain/ReservationAttachment.php
- Domain/ResourceType.php
- Domain/ScheduleLayout.php
- Domain/Quota.php
- Domain/ReservationItemView.php
- Domain/ReservationSeries.php
- Domain/ReservationUserView.php
- Domain/ExistingReservationSeries.php
- Domain/ReservationWaitlistRequest.php
- Domain/ReminderNotice.php
- Domain/Reservation.php
- Domain/ReservationAccessoryView.php
- Domain/SavedReport.php
- Domain/TermsOfService.php
- Domain/ReservationResourceView.php
- Domain/PaymentGateway.php
- Domain/Schedule.php
- Domain/Group.php
- Domain/ReservationReminderView.php
- Domain/Values/AttributeEntityValue.php
- Domain/Values/ReservationPastTimeConstraint.php
- Domain/Values/AttributeValue.php
- Domain/Values/ReservationStartTimeConstraint.php
- Domain/Values/EmailPreferences.php
- Domain/Values/TransactionLogView.php
- Domain/Values/PayPalPaymentResult.php
- Domain/Values/DayOfWeek.php
- Domain/Values/CustomAttributes.php
- Domain/Values/SeriesUpdateScope.php
- Domain/Values/ReservationStatus.php
- Domain/Values/ReservationColorRule.php
- Domain/Values/ReservationReminder.php
- Domain/Values/ResourceStatus.php
- Domain/Values/RoleLevel.php
- Domain/Values/UserPreferences.php
- Domain/Values/AccountStatus.php
- Domain/Values/ResourcePermissionType.php
- Domain/Reminder.php
- Domain/Values/ReservationUserLevel.php
- Domain/Values/CreditLogView.php
- Domain/RepeatOptions.php
- Domain/User.php
- Domain/Values/WebService/WebServiceExpiration.php
- Domain/Values/WebService/WebServiceUserSession.php
- Domain/Values/WebService/WebServiceSessionToken.php
- Domain/Values/ResourceLevel.php
- Domain/Values/FullName.php
- Domain/Values/InvitationAction.php
- Domain/Values/ReservationAccessory.php
- Domain/Events/ReservationEvents.php
- Domain/Events/IDomainEvent.php
- Domain/ResourceGroup.php
- Domain/ReservationAttachmentView.php
- Domain/Blackout.php
- Domain/namespace.php
- Domain/Announcement.php
- Domain/ReservationView.php
- Domain/BookableResource.php
- Domain/Access/AttributeFilter.php
- Domain/Access/BlackoutRepository.php
- Domain/Access/ReportingRepository.php
- Domain/Access/ReportCommandBuilder.php
- Domain/Access/ReservationWaitlistRepository.php
- Domain/Access/UserRepository.php
- Domain/Access/PaymentRepository.php
- Domain/Access/UserPreferenceRepository.php
- Domain/Access/PageableDataStore.php
- Domain/Access/AccessoryRepository.php
- Domain/Access/ReminderRepository.php
- Domain/Access/ReservationViewRepository.php
- Domain/Access/AttributeRepository.php
- Domain/Access/TermsOfServiceRepository.php
- Domain/Access/namespace.php
- Domain/Access/QuotaRepository.php
- Domain/Access/ReservationRepository.php
- Domain/Access/GroupRepository.php
- Domain/Access/IResourceRepository.php
- Domain/Access/CreditRepository.php
- Domain/Access/ResourceRepository.php
- Domain/Access/AnnouncementRepository.php
- Domain/Access/DomainCache.php
- Domain/Access/UserSessionRepository.php
- Domain/Access/ScheduleUserRepository.php
- Domain/Access/ScheduleRepository.php

### Presenters

- Presenters/ViewSchedulesPresenter.php
- Presenters/PasswordPresenter.php
- Presenters/ForgotPwdPresenter.php
- Presenters/SearchAvailabilityPresenter.php
- Presenters/ProfilePresenter.php
- Presenters/LoginPresenter.php
- Presenters/ParticipationPresenter.php
- Presenters/Install/InstallationResult.php
- Presenters/Admin/ManageAccessoriesPresenter.php
- Presenters/Install/MySqlScript.php
- Presenters/Install/ConfigurePresenter.php
- Presenters/Admin/ManageAnnouncementsPresenter.php
- Presenters/Install/InstallSecurityGuard.php
- Presenters/Admin/ManageBlackoutsPresenter.php
- Presenters/Install/Installer.php
- Presenters/Install/InstallPresenter.php
- Presenters/Admin/ManageResourcesPresenter.php
- Presenters/Admin/ManageResourceStatusPresenter.php
- Presenters/Admin/ManageEmailTemplatesPresenter.php
- Presenters/Admin/ManageAttributesPresenter.php
- Presenters/Dashboard/GroupUpcomingReservationsPresenter.php
- Presenters/Admin/ManageResourceTypesPresenter.php
- Presenters/Dashboard/UpcomingReservationsPresenter.php
- Presenters/Dashboard/MissingCheckInOutReservationsPresenter.php
- Presenters/Admin/ManageUsersPresenter.php
- Presenters/Dashboard/ResourceAvailabilityControlPresenter.php
- Presenters/Admin/ManageSchedulesPresenter.php
- Presenters/Dashboard/PendingApprovalReservationsPresenter.php
- Presenters/Admin/ManageThemePresenter.php
- Presenters/Dashboard/AnnouncementPresenter.php
- Presenters/Admin/ManagePaymentsPresenter.php
- Presenters/Dashboard/PastReservationsPresenter.php
- Presenters/Admin/Import/ICalImportPresenter.php
- Presenters/Authentication/ExternalAuthLoginPresenter.php
- Presenters/Admin/ManageConfigurationPresenter.php
- Presenters/Admin/CreditLogPresenter.php
- Presenters/UnavailableResourcesPresenter.php
- Presenters/Admin/ManageQuotasPresenter.php
- Presenters/GuestParticipationPresenter.php
- Presenters/Admin/ManageResourceGroupsPresenter.php
- Presenters/Admin/ManageGroupsPresenter.php
- Presenters/Admin/ManageReservationsPresenter.php
- Presenters/MonitorDisplayPresenter.php
- Presenters/ResourceDisplayPresenter.php
- Presenters/DashboardPresenter.php
- Presenters/CalendarSubscriptionPresenter.php
- Presenters/Schedule/LoadReservationRequest.php
- Presenters/Schedule/SchedulePageBuilder.php
- Presenters/Schedule/SchedulePresenter.php
- Presenters/Integrate/SlackPresenter.php
- Presenters/Credits/CheckoutPresenter.php
- Presenters/Search/SearchReservationsPresenter.php
- Presenters/Credits/UserCreditsPresenter.php
- Presenters/ViewResourcesPresenter.php
- Presenters/NotificationPreferencesPresenter.php
- Presenters/EmbeddedCalendarPresenter.php
- Presenters/CalendarExportPresenter.php
- Presenters/RegistrationPresenter.php
- Presenters/Reports/SavedReportsPresenter.php
- Presenters/Reports/CommonReportsPresenter.php
- Presenters/Reports/ReportCsvColumnView.php
- Presenters/Reports/GenerateReportPresenter.php
- Presenters/Reports/ReportActions.php
- Presenters/Reservation/ReservationDeletePresenter.php
- Presenters/Reservation/ReservationAttachmentPresenter.php
- Presenters/Reservation/ReservationApprovalPresenter.php
- Presenters/Reservation/ReservationCheckinPresenter.php
- Presenters/Reservation/ReservationPresenterFactory.php
- Presenters/Reservation/ReservationWaitlistPresenter.php
- Presenters/Reservation/ReservationMovePresenter.php
- Presenters/Reservation/ReservationUpdatePresenter.php
- Presenters/Reservation/ReservationPresenter.php
- Presenters/Reservation/GuestReservationPresenter.php
- Presenters/Reservation/ReservationSavePresenter.php
- Presenters/Reservation/ReservationUserAvailabilityPresenter.php
- Presenters/Reservation/ReservationEmailPresenter.php
- Presenters/Reservation/ReservationCreditsPresenter.php
- Presenters/Reservation/ReservationAttributesPresenter.php
- Presenters/Reservation/ReservationAttributesPrintPresenter.php
- Presenters/AvailableAccessoriesPresenter.php

### WebServices

- WebServices/UsersWebService.php
- WebServices/AttributesWebService.php
- WebServices/SchedulesWebService.php
- WebServices/GroupsWriteWebService.php
- WebServices/AuthenticationWebService.php
- WebServices/ReservationWriteWebService.php
- WebServices/ReservationsWebService.php
- WebServices/ResourcesWriteWebService.php
- WebServices/AccessoriesWebService.php
- WebServices/UsersWriteWebService.php
- WebServices/Validators/RequestRequiredValueValidator.php
- WebServices/Validators/ResourceRequestValidator.php
- WebServices/Validators/UserRequestValidator.php
- WebServices/Validators/TimeIntervalValidator.php
- WebServices/Validators/AccountRequestValidator.php
- WebServices/GroupsWebService.php
- WebServices/Controllers/GroupSaveController.php
- WebServices/AttributesWriteWebService.php
- WebServices/Controllers/ReservationSaveController.php
- WebServices/Controllers/UserSaveController.php
- WebServices/ResourcesWebService.php
- WebServices/Controllers/AccountController.php
- WebServices/Controllers/AttributeSaveController.php
- WebServices/Controllers/ResourceSaveController.php
- WebServices/Requests/CustomAttributes/CustomAttributeRequest.php
- WebServices/Requests/CustomAttributes/AttributeValueRequest.php
- WebServices/Responses/ResourceItemResponse.php
- WebServices/Requests/Group/GroupPermissionsRequest.php
- WebServices/Requests/Group/GroupRolesRequest.php
- WebServices/Requests/Group/GroupRequest.php
- WebServices/Requests/Group/GroupUsersRequest.php
- WebServices/Responses/Group/GroupResponse.php
- WebServices/Requests/AuthenticationRequest.php
- WebServices/Responses/Group/GroupCreatedResponse.php
- WebServices/Responses/Group/GroupItemResponse.php
- WebServices/Responses/Group/GroupsResponse.php
- WebServices/Requests/User/UpdateUserRequest.php
- WebServices/Responses/SchedulesResponse.php
- WebServices/Requests/Resource/ResourceRequest.php
- WebServices/Requests/User/UpdateUserPasswordRequest.php
- WebServices/Responses/ReservationResponse.php
- WebServices/Requests/User/UserRequestBase.php
- WebServices/Requests/ReservationRequest.php
- WebServices/Responses/ResourcesResponse.php
- WebServices/Requests/User/CreateUserRequest.php
- WebServices/Responses/UserCreatedResponse.php
- WebServices/Requests/SignOutRequest.php
- WebServices/Responses/ScheduleItemResponse.php
- WebServices/Requests/ReservationAccessoryRequest.php
- WebServices/Responses/AttachmentResponse.php
- WebServices/Responses/Resource/ResourceStatusResponse.php
- WebServices/Responses/Resource/ResourceCreatedResponse.php
- WebServices/Responses/Resource/ResourceTypesResponse.php
- WebServices/Responses/Resource/ResourceGroupsResponse.php
- WebServices/Responses/Resource/ResourceUpdatedResponse.php
- WebServices/Responses/Resource/ResourceReference.php
- WebServices/Responses/Resource/ResourceAvailabilityResponse.php
- WebServices/Responses/Resource/ResourceStatusReasonsResponse.php
- WebServices/Responses/ReminderRequestResponse.php
- WebServices/Responses/Account/AccountActionResponse.php
- WebServices/Responses/Account/AccountResponse.php
- WebServices/Responses/ReservationUserResponse.php
- WebServices/Responses/Reservation/ReservationRetryParameterRequestResponse.php
- WebServices/Responses/ResourceResponse.php
- WebServices/Responses/ReservationCreatedResponse.php
- WebServices/Responses/ReservationAccessoryResponse.php
- WebServices/Responses/Schedule/ScheduleSlotsResponse.php
- WebServices/Responses/AuthenticationResponse.php
- WebServices/Responses/ReservationsResponse.php
- WebServices/Responses/ScheduleResponse.php
- WebServices/Responses/AccessoriesResponse.php
- WebServices/Responses/UserResponse.php
- WebServices/Responses/UserItemResponse.php
- WebServices/Responses/AccessoryResponse.php
- WebServices/Responses/UsersResponse.php
- WebServices/Responses/UserUpdatedResponse.php
- WebServices/Responses/CustomAttributes/CustomAttributeResponse.php
- WebServices/Responses/CustomAttributes/CustomAttributesResponse.php
- WebServices/Responses/CustomAttributes/CustomAttributeDefinitionResponse.php
- WebServices/Responses/CustomAttributes/CustomAttributeCreatedResponse.php
- WebServices/Responses/RecurrenceRequestResponse.php
- WebServices/Responses/ReservationItemResponse.php
- WebServices/AccountWebService.php

### lib

The `lib` tree still contains extensive legacy code (approximately 331 PHP files) without namespaces. Major areas include:

- `lib/Email/*`
- `lib/FileSystem/*`
- `lib/Config/*`
- `lib/Database/*`
- `lib/WebService/*`
- `lib/Graphics/*`
- `lib/Server/*`
- `lib/Application/*` (Authorization, Admin, Reservation, Schedule, etc.)
- `lib/Common/*` (remaining subfolders)

Run `rg --files-without-match -t php '^\s*namespace' lib/` to view the full list when tackling these directories.

## Recent Progress

- Removed intra-page `require_once` calls; pages now rely on Composer autoloader.

## Next Steps

1. Start with the `Domain` folder, adding namespaces and class aliases for the files listed above.
2. Proceed to the `Presenters` directory, namespacing each presenter and removing obsolete `require_once` statements.
3. Migrate `WebServices` classes, ensuring request/response DTOs and controllers use the new namespace structure.
4. Finish by organizing the remaining `lib` modules into appropriate namespaces (`LibreBooking\Email`, `LibreBooking\Config`, etc.).
5. After each directory is refactored, run `composer phpunit` and address any missing dependency errors.
