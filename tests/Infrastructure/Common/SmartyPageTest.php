<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Common/namespace.php');

use PHPUnit\Framework\Attributes\DataProvider;

class SmartyPageTest extends TestBase
{
    public function testCreateUrlLinkifiesHttpAndHttpsUrls(): void
    {
        $page = new SmartyPage();

        $this->assertStringContainsString(
            '<a href="http://example.com"',
            $page->CreateUrl('visit http://example.com today')
        );
        $this->assertStringContainsString(
            '<a href="https://example.com/path"',
            $page->CreateUrl('visit https://example.com/path today')
        );
    }

    public function testCreateUrlForcesNoopenerNoreferrerOnGeneratedLinks(): void
    {
        $page = new SmartyPage();

        $this->assertStringContainsString(
            'rel="noopener noreferrer nofollow"',
            $page->CreateUrl('visit https://example.com today')
        );
    }

    public function testCreateUrlDoesNotLinkifyJavascriptScheme(): void
    {
        $page = new SmartyPage();

        $actual = $page->CreateUrl('click javascript://%0Aalert%281%29 now');

        $this->assertStringNotContainsString('<a', $actual);
        $this->assertStringNotContainsString('href=', $actual);
    }

    public function testCreateUrlDoesNotLinkifyNonHttpSchemes(): void
    {
        $page = new SmartyPage();

        $this->assertStringNotContainsString('<a', $page->CreateUrl('x ftp://host/file y'));
        $this->assertStringNotContainsString('<a', $page->CreateUrl('x data://text/html,x y'));
    }

    public function testCreateUrlLinkifiesValidEmail(): void
    {
        $page = new SmartyPage();

        $this->assertStringContainsString(
            'mailto:user@example.com',
            $page->CreateUrl('mail user@example.com please')
        );
    }

    #[DataProvider('escapeCsvValues')]
    public function testEscapeCsvDoublesDoubleQuotesOnly(mixed $value, string $expected): void
    {
        $page = new SmartyPage();

        $this->assertSame($expected, $page->EscapeCsv($value));
    }

    /**
     * @return array<string, array{0:mixed, 1:string}>
     */
    public static function escapeCsvValues(): array
    {
        return [
            'plain text' => ['plain', 'plain'],
            'double quotes are doubled' => ['say "hi"', 'say ""hi""'],
            'single quotes unchanged' => ["O'Brien", "O'Brien"],
            'backslashes unchanged' => ['C:\\path', 'C:\\path'],
            'commas and newlines unchanged' => ["a,b\nc", "a,b\nc"],
            'null becomes empty string' => [null, ''],
            'integer is stringified' => [42, '42'],
        ];
    }

    public function testDisplayFullNameEncodesHtmlByDefault(): void
    {
        $page = new SmartyPage();

        $this->assertSame(
            'Mary &#039;Mae&#039; &quot;O&quot;',
            $page->DisplayFullName(['first' => "Mary 'Mae'", 'last' => '"O"'], $page)
        );
    }

    public function testDisplayFullNameEscapesForCsvWhenRequested(): void
    {
        $page = new SmartyPage();

        $this->assertSame(
            'Mary \'Mae\' ""O""',
            $page->DisplayFullName(['first' => "Mary 'Mae'", 'last' => '"O"', 'csv' => true], $page)
        );
    }

    public function testDisplayFullNameHonorsPrivacyForCsv(): void
    {
        $this->fakeConfig->SetKey(ConfigKeys::PRIVACY_HIDE_USER_DETAILS, true);
        $this->fakeUser->IsAdmin = false;
        $page = new SmartyPage();

        $this->assertSame(
            'Private',
            $page->DisplayFullName(['first' => 'Mary', 'last' => 'Smith', 'csv' => true], $page)
        );
    }

    public function testEscapeCsvModifierIsRegistered(): void
    {
        $page = new SmartyPage();
        $page->assign('value', 'say "hi"');

        $this->assertSame('"say ""hi"""', $page->fetch('string:"{$value|escape_csv}"'));
    }
}
