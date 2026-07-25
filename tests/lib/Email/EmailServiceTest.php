<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Email/EmailService.php');

class EmailServiceTest extends TestBase
{
    public function setUp(): void
    {
        parent::setup();
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testAttachmentsAreClearedBeforeEachSendToPreventLeakingIntoSubsequentEmails()
    {
        $phpMailer = $this->createMock('PHPMailer\PHPMailer\PHPMailer');

        $phpMailer->expects($this->exactly(2))->method('clearAttachments');

        $message1 = new FakeEmailMessage();
        $message1->AddStringAttachment('first attachment', 'first.ics');

        $message2 = new FakeEmailMessage();
        $message2->AddStringAttachment('second attachment', 'second.ics');

        $service = new EmailService($phpMailer);
        $service->Send($message1);
        $service->Send($message2);
    }
}
