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

namespace OAT\Library\Lti1p3SubmissionReview\Tests\Unit\Message\Launch\Builder;

use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ForUserClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayload;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Core\Tests\Traits\DomainTestingTrait;
use OAT\Library\Lti1p3Core\Tool\Tool;
use OAT\Library\Lti1p3SubmissionReview\Message\Launch\Builder\SubmissionReviewLaunchRequestBuilder;
use PHPUnit\Framework\TestCase;

class SubmissionReviewLaunchRequestBuilderTest extends TestCase
{
    use DomainTestingTrait;

    /** @var RegistrationInterface */
    private $registration;

    /** @var SubmissionReviewLaunchRequestBuilder */
    private $subject;

    protected function setUp(): void
    {
        $this->registration = $this->createTestRegistration();

        $this->subject = new SubmissionReviewLaunchRequestBuilder();
    }

    public function testBuildSubmissionReviewLaunchRequestSuccess(): void
    {
        $agsClaim = $this->createTestAgsClaim();
        $forUserClaim = $this->createTestForUserClaim();

        $result = $this->subject->buildSubmissionReviewLaunchRequest(
            $agsClaim,
            $forUserClaim,
            $this->registration,
            'loginHint',
            'http://tool.com/submission-review-url',
            null,
            [
                'Instructor'
            ],
            [
                'a' => 'b'
            ]
        );

        $this->assertInstanceOf(LtiMessageInterface::class, $result);

        $this->assertEquals(
            'http://tool.com/submission-review-url',
            $result->getParameters()->getMandatory('target_link_uri')
        );

        $ltiMessageHintToken = $this->parseJwt($result->getParameters()->getMandatory('lti_message_hint'));

        $this->assertTrue(
            $this->verifyJwt($ltiMessageHintToken, $this->registration->getPlatformKeyChain()->getPublicKey())
        );

        $payload = new LtiMessagePayload($ltiMessageHintToken);

        $this->assertEquals($agsClaim->getLineItemUrl(), $payload->getAgs()->getLineItemUrl());
        $this->assertEquals($forUserClaim->getIdentifier(), $payload->getForUser()->getIdentifier());
        $this->assertEquals(['Instructor'], $payload->getRoles());
        $this->assertEquals('b', $payload->getClaim('a'));
    }

    public function testLtiResourceLinkBuildSubmissionReviewLaunchRequestSuccess(): void
    {
        $ltiResourceLink = new LtiResourceLink(
            'resourceLinkIdentifier',
            [
                'url' => 'http://tool.com/resource-link-submission-review-url'
            ]
        );
        $agsClaim = $this->createTestAgsClaim();
        $forUserClaim = $this->createTestForUserClaim();

        $result = $this->subject->buildLtiResourceLinkSubmissionReviewLaunchRequest(
            $ltiResourceLink,
            $agsClaim,
            $forUserClaim,
            $this->registration,
            'loginHint',
            'http://tool.com/submission-review-url',
            null,
            [
                'Instructor'
            ],
            [
                'a' => 'b'
            ]
        );

        $this->assertInstanceOf(LtiMessageInterface::class, $result);

        $this->assertEquals(
            'http://tool.com/resource-link-submission-review-url',
            $result->getParameters()->getMandatory('target_link_uri')
        );

        $ltiMessageHintToken = $this->parseJwt($result->getParameters()->getMandatory('lti_message_hint'));

        $this->assertTrue(
            $this->verifyJwt($ltiMessageHintToken, $this->registration->getPlatformKeyChain()->getPublicKey())
        );

        $payload = new LtiMessagePayload($ltiMessageHintToken);

        $this->assertEquals($ltiResourceLink->getIdentifier(), $payload->getResourceLink()->getIdentifier());
        $this->assertEquals($agsClaim->getLineItemUrl(), $payload->getAgs()->getLineItemUrl());
        $this->assertEquals($forUserClaim->getIdentifier(), $payload->getForUser()->getIdentifier());
        $this->assertEquals(['Instructor'], $payload->getRoles());
        $this->assertEquals('b', $payload->getClaim('a'));
    }

    public function testBuildSubmissionReviewLaunchRequestFailureOnMissingAgsLineItemUrl(): void
    {
        $agsClaim = $this->createTestAgsClaim(null);
        $forUserClaim = $this->createTestForUserClaim();

        $this->expectException(LtiExceptionInterface::class);
        $this->expectExceptionMessage('Cannot create submission review launch request: Missing line item url from AGS claim');

        $this->subject->buildSubmissionReviewLaunchRequest(
            $agsClaim,
            $forUserClaim,
            $this->registration,
            'loginHint',
            'http://tool.com/submission-review-url',
            null,
            [
                'Instructor'
            ],
            [
                'a' => 'b'
            ]
        );
    }

    public function testBuildSubmissionReviewLaunchRequestFailureOnMissingLaunchUrl(): void
    {
        $tool = new Tool(
            'toolIdentifier',
            'toolName',
            'toolAudience',
            'http://tool.com/oidc-init'
        );

        $registration  = $this->createTestRegistration(
            'registrationIdentifier',
            'registrationClientId',
            $this->createTestPlatform(),
            $tool,
            ['deploymentIdentifier']
        );

        $this->expectException(LtiExceptionInterface::class);
        $this->expectExceptionMessage('Neither submission review url nor tool default url were presented');

        $this->subject->buildSubmissionReviewLaunchRequest(
            $this->createTestAgsClaim(),
            $this->createTestForUserClaim(),
            $registration,
            'loginHint'
        );
    }

    private function createTestAgsClaim(?string $lineItemUrl = 'http://platform.com/lineitems/1'): AgsClaim
    {
        return new AgsClaim(
            [
                'https://purl.imsglobal.org/spec/lti-ags/scope/score'
            ],
            'http://platform.com/lineitems',
            $lineItemUrl
        );
    }

    private function createTestForUserClaim(): ForUserClaim
    {
        return  new ForUserClaim('userIdentifier');
    }
}
