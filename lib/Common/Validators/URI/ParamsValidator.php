<?php

use LibreBooking\Common\ScriptDirectoryUrl;

class ParamsValidator
{
    /**
     * Validates query parameters in the request URI against predefined rules.
     *
     * @param array $params       Parameter definitions and validation rules
     * @param string $requestURI  The full request URI (e.g., $_SERVER['REQUEST_URI'])
     * @param bool $optional      Whether validation is optional
     * @return bool               True if validation passed or skipped; false if validation failed
     */
    public static function validate(array $params, string $requestURI, bool $optional): bool
    {
        // Parse query string from the URI into an array
        $query = parse_url($requestURI, PHP_URL_QUERY) ?? '';
        parse_str($query, $queryParams);

        // If there are no query parameters
        if (empty($queryParams)) {
            Log::Warning(message: 'No parameters found in URI.');

            if ($optional) {
                Log::Warning(message: 'Validation optional. Skipping.');
                return true;
            } else {
                Log::Warning(message: 'Parameters required but missing.');
                return false;
            }
        }

        // Loop through all defined validation rules
        foreach ($params as $param => $validators) {
            $validators = (array) $validators;

            if (!array_key_exists($param, $queryParams)) {
                Log::Warning("Skipping param '%s': not present in request.", $param);
                continue;
            }

            if (!self::validateParam($param, $validators, $requestURI)) {
                Log::Warning("Validation failed for '%s'.", $param);
                return false;
            }

            Log::Debug("Validation passed for '%s'.", $param);
        }
        return true;
    }

    public static function validateOrRedirect(array $params, string $requestURI, string $redirectURL, bool $optional): void
    {
        if (!self::validate($params, $requestURI, $optional)) {
            $location = ScriptDirectoryUrl::forPage(
                scriptName: $_SERVER['SCRIPT_NAME'] ?? '',
                page: $redirectURL,
            );
            Log::Warning('Validation failed. Redirecting to: %s', $location);
            header('Location: ' . $location);
            exit;
        }
    }

    /**
     * Validates a single parameter using its associated validation rules.
     *
     * Supports both normal validators (e.g., numerical, date) and
     * match validators (where the value must match one of a list).
     */
    private static function validateParam(string $param, array $validators, string $requestURI): bool
    {
        // Track whether this param has a MATCH or normal validator and whether it passes
        $hasMatch = false;
        $passedMatch = false;
        $hasNormal = false;
        $passedNormal = false;

        Log::Debug("Validating param '%s' with rules: %s", $param, json_encode($validators));

        foreach ($validators as $validator) {
            // MATCH validator (array of expected values)
            if (is_array($validator)) {
                $hasMatch = true;

                foreach ($validator as $expected) {
                    $result = ParamsValidatorMethods::matchValidator($param, $expected, $requestURI);
                    Log::Debug("MATCH validation for '%s' against expected '%s': %s", $param, $expected, $result ? 'passed' : 'failed');

                    if ($result) {
                        $passedMatch = true;
                        break;
                    }
                }

                // Simple validator (like 'n' for numerical, 'd' for date)
            } else {
                $hasNormal = true;

                $result = self::runSimpleValidation($param, $validator, $requestURI);
                Log::Debug("Validator '%s' for '%s': %s", $validator, $param, $result ? 'passed' : 'failed');

                if ($result) {
                    $passedNormal = true;
                }
            }
        }

        // Param is valid if either:
        // - No MATCH validator is defined OR one MATCH validator passed
        // AND
        // - No normal validator is defined OR one normal validator passed
        $finalResult = (!$hasMatch || $passedMatch) && (!$hasNormal || $passedNormal);
        Log::Debug("Final validation result for '%s': %s", $param, $finalResult ? 'passed' : 'failed');

        return $finalResult;
    }

    /**
     * Runs a basic single-type validation (non-MATCH).
     *
     * @param string $param     Parameter name
     * @param string $validator Validator key (e.g. 'n', 'd', etc.)
     * @param string $requestURI Full request URI to extract value
     */
    private static function runSimpleValidation(string $param, string $validator, string $requestURI): bool
    {
        switch ($validator) {
            case ParamsValidatorKeys::NUMERICAL:
                return ParamsValidatorMethods::numericalValidator($param, $requestURI);

            case ParamsValidatorKeys::DATE:
                return ParamsValidatorMethods::dateValidator($param, $requestURI);

            case ParamsValidatorKeys::SIMPLE_DATE:
                return ParamsValidatorMethods::simpleDateValidatorList($param, $requestURI);

            case ParamsValidatorKeys::SIMPLE_DATETIME:
                return ParamsValidatorMethods::simpleDateTimeValidator($param, $requestURI);

            case ParamsValidatorKeys::COMPLEX_DATETIME:
                return ParamsValidatorMethods::complexDateTimedateValidator($param, $requestURI);

            case ParamsValidatorKeys::EXISTS:
                return ParamsValidatorMethods::existsInURLValidator($param, $requestURI);

            case ParamsValidatorKeys::OPTIONAL_SIMPLEDATE:
                return ParamsValidatorMethods::optionalSimpleDateValidator($param, $requestURI);

            case ParamsValidatorKeys::REDIRECT_GUEST_RESERVATION:
                return ParamsValidatorMethods::redirectGuestReservationValidator($requestURI);

            case ParamsValidatorKeys::OPTIONAL_NUMERIC:
                return ParamsValidatorMethods::optionalNumericValidator($param, $requestURI);

            case ParamsValidatorKeys::BOOLEAN:
                return ParamsValidatorMethods::booleanValidator($param, $requestURI);

            default:
                Log::Warning("Unknown validator '%s' for param '%s'. Failing by default.", $validator, $param);
                return false;
        }
    }
}
