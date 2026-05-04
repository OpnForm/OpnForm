<?php

use App\Open\MentionParser;

describe('MentionParser', function () {
    it('replaces mentions with their values in HTML', function () {
        $content = '<div>Hello <span mention mention-field-id="123" mention-fallback="">Name</span></div>';
        $data = [
            ['id' => '123', 'value' => 'John Doe']
        ];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<div>Hello John Doe</div>');
    });

    it('uses fallback when value is not found', function () {
        $content = '<span mention mention-field-id="456" mention-fallback="Guest">Name</span>';
        $data = [];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('Guest');
    });

    it('uses zero fallback when value is not found', function () {
        $content = '<span mention mention-field-id="456" mention-fallback="0">Name</span>';
        $data = [];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('0');
    });

    it('removes the element when no value and no fallback is provided', function () {
        $content = '<div>Hello <span mention mention-field-id="789" mention-fallback="">Name</span>!</div>';
        $data = [];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<div>Hello !</div>');
    });

    it('supports mention="true" syntax', function () {
        $content = '<p>Hello <span mention="true" mention-field-id="123" mention-fallback="">Full Name</span></p>';
        $data = [
            ['id' => '123', 'value' => 'John Doe']
        ];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello John Doe</p>');
    });

    describe('parseAsText', function () {
        it('converts HTML to plain text with proper line breaks', function () {
            $content = '<div>First line</div><div>Second line</div>';

            $parser = new MentionParser($content, []);
            $result = $parser->parseAsText();

            expect($result)->toBe("First line\nSecond line");
        });

        it('handles email addresses with proper line breaks', function () {
            $content = '<span mention mention-field-id="123" mention-fallback="">Email</span><div>john@example.com</div>';
            $data = [
                ['id' => '123', 'value' => 'jane@example.com']
            ];

            $parser = new MentionParser($content, $data);
            $result = $parser->parseAsText();

            expect($result)->toBe("jane@example.com\njohn@example.com");
        });

        it('handles multiple mentions and complex HTML structure', function () {
            $content = '
                <div>Contact: <span mention mention-field-id="123" mention-fallback="">Email1</span></div>
                <div>CC: <span mention mention-field-id="456" mention-fallback="">Email2</span></div>
                <div>Additional: test@example.com</div>
            ';
            $data = [
                ['id' => '123', 'value' => 'primary@example.com'],
                ['id' => '456', 'value' => 'secondary@example.com'],
            ];

            $parser = new MentionParser($content, $data);
            $result = $parser->parseAsText();

            expect($result)->toBe(
                "Contact: primary@example.com\n" .
                    "CC: secondary@example.com\n" .
                    "Additional: test@example.com"
            );
        });

        it('handles array values in mentions', function () {
            $content = '<span mention mention-field-id="123" mention-fallback="">Emails</span>';
            $data = [
                ['id' => '123', 'value' => ['first@test.com', 'second@test.com']]
            ];

            $parser = new MentionParser($content, $data);
            $result = $parser->parseAsText();

            expect($result)->toBe('first@test.com, second@test.com');
        });
    });

    test('it replaces mention elements with their corresponding values', function () {
        $content = '<p>Hello <span mention mention-field-id="123">Placeholder</span></p>';
        $data = [['id' => '123', 'value' => 'World']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello World</p>');
    });

    test('it handles multiple mentions', function () {
        $content = '<p><span mention mention-field-id="123">Name</span> is <span mention mention-field-id="456">Age</span> years old</p>';
        $data = [
            ['id' => '123', 'value' => 'John'],
            ['id' => '456', 'value' => 30],
        ];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>John is 30 years old</p>');
    });

    test('it uses fallback when value is not found', function () {
        $content = '<p>Hello <span mention mention-field-id="123" mention-fallback="Friend">Placeholder</span></p>';
        $data = [];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello Friend</p>');
    });

    test('it removes mention element when no value and no fallback', function () {
        $content = '<p>Hello <span mention mention-field-id="123">Placeholder</span></p>';
        $data = [];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello </p>');
    });

    test('it handles array values', function () {
        $content = '<p>Tags: <span mention mention-field-id="123">Placeholder</span></p>';
        $data = [['id' => '123', 'value' => ['PHP', 'Laravel', 'Testing']]];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Tags: PHP, Laravel, Testing</p>');
    });

    test('it supports mention="true" attributes', function () {
        $content = '<p>Hello <span contenteditable="false" mention="true" mention-field-id="123" mention-field-name="Full Name" mention-fallback="">Full Name</span></p>';
        $data = [['id' => '123', 'value' => 'John Doe']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello John Doe</p>');
    });

    test('it handles multiple mentions with mention="true" syntax', function () {
        $content = '<p><span contenteditable="false" mention="true" mention-field-id="123" mention-field-name="Name" mention-fallback="">Name</span> and <span contenteditable="false" mention="true" mention-field-id="456" mention-field-name="Title" mention-fallback="">Title</span></p>';
        $data = [
            ['id' => '123', 'value' => 'John Doe'],
            ['id' => '456', 'value' => 'Developer'],
        ];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>John Doe and Developer</p>');
    });

    test('it preserves HTML structure', function () {
        $content = '<div><p>Hello <span mention mention-field-id="123">Placeholder</span></p><p>How are you?</p></div>';
        $data = [['id' => '123', 'value' => 'World']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<div><p>Hello World</p><p>How are you?</p></div>');
    });

    test('it handles UTF-8 characters', function () {
        $content = '<p>こんにちは <span mention mention-field-id="123">Placeholder</span></p>';
        $data = [['id' => '123', 'value' => '世界']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>こんにちは 世界</p>');
    });

    test('it handles content without surrounding paragraph tags', function () {
        $content = 'some text <span contenteditable="false" mention="" mention-field-id="123" mention-field-name="Post excerpt" mention-fallback="">Post excerpt</span> dewde';
        $data = [['id' => '123', 'value' => 'replaced text']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('some text replaced text dewde');
    });

    test('it handles URL-encoded field IDs', function () {
        $content = '<p>Hello <span mention mention-field-id="%3ARGE" mention-fallback="">Full Name</span></p>';
        $data = [['id' => '%3ARGE', 'value' => 'John Doe']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello John Doe</p>');
    });

    test('it handles URL-encoded field IDs with mention="true" syntax', function () {
        $content = '<p>Hello <span contenteditable="false" mention="true" mention-field-id="%3ARGE" mention-field-name="Full Name" mention-fallback="">Full Name</span></p>';
        $data = [['id' => '%3ARGE', 'value' => 'John Doe']];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello John Doe</p>');
    });

    test('it handles multiple mentions with URL-encoded IDs and mention="true" syntax', function () {
        $content = '<p><span contenteditable="false" mention="true" mention-field-id="%3ARGE" mention-field-name="Full Name" mention-fallback="">Full Name</span> and <span contenteditable="false" mention="true" mention-field-id="V%7D%40S" mention-field-name="Phone Number" mention-fallback="">Phone</span></p>';
        $data = [
            ['id' => '%3ARGE', 'value' => 'John Doe'],
            ['id' => 'V%7D%40S', 'value' => '123-456-7890'],
        ];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>John Doe and 123-456-7890</p>');
    });

    test('it recreates real-world example with URL-encoded IDs', function () {
        $content = '<p>Hello there 👋 </p><p>This is a confirmation that your submission was successfully saved.</p><p><span contenteditable="false" mention="true" mention-field-id="%3ARGE" mention-field-name="Full Name" mention-fallback="">Full Name</span><span contenteditable="false" mention="true" mention-field-id="title" mention-field-name="Contact Form" mention-fallback="">Contact Form</span><span contenteditable="false" mention="true" mention-field-id="V%7D%40S" mention-field-name="Phone Number" mention-fallback="">Phone Number</span></p>';
        $data = [
            ['id' => '%3ARGE', 'value' => 'jujujujuju'],
            ['id' => 'title', 'value' => 'jujuuj'],
            ['id' => 'V%7D%40S', 'value' => '555-1234'],
        ];

        $parser = new MentionParser($content, $data);
        $result = $parser->parse();

        expect($result)->toBe('<p>Hello there 👋 </p><p>This is a confirmation that your submission was successfully saved.</p><p>jujujujujujujuuj555-1234</p>');
    });

    describe('urlFriendlyOutput', function () {
        test('it encodes special characters in values', function () {
            $content = '<p>Test: <span mention mention-field-id="123">Placeholder</span></p>';
            $data = [['id' => '123', 'value' => 'Hello & World']];

            $parser = new MentionParser($content, $data);
            $result = $parser->urlFriendlyOutput()->parse();

            expect($result)->toBe('<p>Test: Hello+%26+World</p>');
        });

        test('it encodes spaces in values', function () {
            $content = '<p>Name: <span mention mention-field-id="123">Placeholder</span></p>';
            $data = [['id' => '123', 'value' => 'John Doe']];

            $parser = new MentionParser($content, $data);
            $result = $parser->urlFriendlyOutput()->parse();

            expect($result)->toBe('<p>Name: John+Doe</p>');
        });

        test('it encodes array values', function () {
            $content = '<p>Tags: <span mention mention-field-id="123">Placeholder</span></p>';
            $data = [['id' => '123', 'value' => ['Web & Mobile', 'PHP/Laravel']]];

            $parser = new MentionParser($content, $data);
            $result = $parser->urlFriendlyOutput()->parse();

            expect($result)->toBe('<p>Tags: Web+%26+Mobile,+PHP%2FLaravel</p>');
        });

        test('it can be disabled explicitly', function () {
            $content = '<p>Test: <span mention mention-field-id="123">Placeholder</span></p>';
            $data = [['id' => '123', 'value' => 'Hello & World']];

            $parser = new MentionParser($content, $data);
            $result = $parser->urlFriendlyOutput(false)->parse();

            expect($result)->toBe('<p>Test: Hello & World</p>');
        });
    });
});
