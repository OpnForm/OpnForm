<?php

use App\Service\HtmlPurifier\OpenFormsHtmlDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Config
    |--------------------------------------------------------------------------
    |
    | This option defines the default config that is provided to HTMLPurifier.
    |
    */

    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Config sets
    |--------------------------------------------------------------------------
    |
    | Here you may configure various sets of configuration for differentiated use of HTMLPurifier.
    | A specific set of configuration can be applied by calling the "config($name)" method on
    | a Purify instance. Feel free to add/remove/customize these attributes as you wish.
    |
    | Documentation: http://htmlpurifier.org/live/configdoc/plain.html
    |
    |   Core.Encoding               The encoding to convert input to.
    |   HTML.Doctype                Doctype to use during filtering.
    |   HTML.Allowed                The allowed HTML Elements with their allowed attributes.
    |   HTML.ForbiddenElements      The forbidden HTML elements. Elements that are listed in this
    |                               string will be removed, however their content will remain.
    |   CSS.AllowedProperties       The Allowed CSS properties.
    |   AutoFormat.AutoParagraph    Newlines are converted in to paragraphs whenever possible.
    |   AutoFormat.RemoveEmpty      Remove empty elements that contribute no semantic information to the document.
    |
    */

    'configs' => [

        'default' => [
            'HTML.Allowed' => 'h1,h2,b,u,strong,i,em,a[href|title],ul,ol,li,p,br,span[mention|mention-field-id|mention-field-name|mention-fallback],*[style]',
            'HTML.ForbiddenElements' => '',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,text-decoration,color,text-align',

            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,

            /*
             * Protect Links
             */
            'HTML.Nofollow' => true,
            'HTML.TargetBlank' => true,
            'HTML.TargetNoreferrer' => true,
            'HTML.TargetNoopener' => true,

            /*
             * Allow Notion links: allow Notion custom protocol
             */
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
                'tel' => true,
                'notion' => true,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier definitions
    |--------------------------------------------------------------------------
    |
    | Here you may specify a class that augments the HTML definitions used by
    | HTMLPurifier. Additional HTML5 definitions are provided out of the box.
    | When specifying a custom class, make sure it implements the interface:
    |
    |   \Stevebauman\Purify\Definitions\Definition
    |
    | Note that these definitions are applied to every Purifier instance.
    |
    | Documentation: http://htmlpurifier.org/docs/enduser-customize.html
    |
    */

    'definitions' => OpenFormsHtmlDefinition::class,

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier CSS definitions
    |--------------------------------------------------------------------------
    |
    | Here you may specify a class that augments the CSS definitions used by
    | HTMLPurifier. When specifying a custom class, make sure it implements
    | the interface:
    |
    |   \Stevebauman\Purify\Definitions\CssDefinition
    |
    | Note that these definitions are applied to every Purifier instance.
    |
    | CSS should be extending $definition->info['css-attribute'] = values
    | See HTMLPurifier_CSSDefinition for further explanation
    |
    */

    'css-definitions' => null,

    /*
    |--------------------------------------------------------------------------
    | Serializer
    |--------------------------------------------------------------------------
    |
    | The storage implementation where HTMLPurifier can store its serializer files.
    | If the filesystem cache is in use, the path must be writable through the
    | storage disk by the web server, otherwise an exception will be thrown.
    |
    */

    'serializer' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'cache' => \Stevebauman\Purify\Cache\CacheDefinitionCache::class,
    ],

    // 'serializer' => [
    //    'disk' => env('FILESYSTEM_DRIVER', 'local'),
    //    'path' => 'purify',
    //    'cache' => \Stevebauman\Purify\Cache\FilesystemDefinitionCache::class,
    // ],

];
