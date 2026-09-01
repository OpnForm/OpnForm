<?php

describe('app_url helper', function () {
    it('combines the canonical application URL with a relative path', function () {
        config()->set('app.url', 'https://forms.example.test/');

        expect(app_url('/open/forms/123?signature=test'))
            ->toBe('https://forms.example.test/open/forms/123?signature=test');
    });

    it('returns the relative path when no application URL is configured', function () {
        config()->set('app.url', null);

        expect(app_url('/open/forms/123'))->toBe('/open/forms/123');
    });
});
