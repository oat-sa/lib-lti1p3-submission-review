<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\Lti1p3SubmissionReview\Message\Launch\Builder;

use InvalidArgumentException;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ForUserClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use Throwable;

/**
 * @see https://www.imsglobal.org/spec/lti-sr/v1p0
 */
class SubmissionReviewLaunchRequestBuilder extends PlatformOriginatingLaunchBuilder
{
    /**
     * @throws LtiExceptionInterface
     */
    public function buildSubmissionReviewLaunchRequest(
        AgsClaim $agsClaim,
        ForUserClaim $forUserClaim,
        RegistrationInterface $registration,
        string $loginHint,
        string $submissionReviewUrl = null,
        string $deploymentId = null,
        array $roles = [],
        array $optionalClaims = []
    ): LtiMessageInterface {
        try {
            if (null === $agsClaim->getLineItemUrl()) {
                throw new InvalidArgumentException('Missing line item url from AGS claim');
            }

            $this->builder
                ->withClaim($agsClaim)
                ->withClaim($forUserClaim);

            $launchUrl = $submissionReviewUrl ?? $registration->getTool()->getLaunchUrl();

            if (null === $launchUrl) {
                throw new LtiException('Neither submission review url nor tool default url were presented');
            }

            return $this->buildPlatformOriginatingLaunch(
                $registration,
                LtiMessageInterface::LTI_MESSAGE_TYPE_SUBMISSION_REVIEW_REQUEST,
                $launchUrl,
                $loginHint,
                $deploymentId,
                $roles,
                $optionalClaims
            );

        } catch (LtiExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LtiException(
                sprintf('Cannot create submission review launch request: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function buildLtiResourceLinkSubmissionReviewLaunchRequest(
        LtiResourceLinkInterface $ltiResourceLink,
        AgsClaim $agsClaim,
        ForUserClaim $forUserClaim,
        RegistrationInterface $registration,
        string $loginHint,
        string $submissionReviewUrl = null,
        string $deploymentId = null,
        array $roles = [],
        array $optionalClaims = []
    ): LtiMessageInterface {

        $this->builder->withClaim(
            ResourceLinkClaim::denormalize([
                'id' => $ltiResourceLink->getIdentifier(),
                'title' => $ltiResourceLink->getTitle(),
                'description' => $ltiResourceLink->getText(),
            ])
        );

        $launchUrl = $ltiResourceLink->getUrl() ?? $submissionReviewUrl;

        return $this->buildSubmissionReviewLaunchRequest(
            $agsClaim,
            $forUserClaim,
            $registration,
            $loginHint,
            $launchUrl,
            $deploymentId,
            $roles,
            $optionalClaims
        );
    }
}
