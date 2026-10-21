<?php

namespace App\Modules\LocalGovernment\Application;

use App\Models\MunicipalityWebsite;
use InvalidArgumentException;

final class LguWebsiteAddress
{
    // These names belong to the central application, hosting controls or the
    // isolated staff demo. They must never be claimed by an LGU website.
    public const RESERVED = ['www', 'app', 'admin', 'api', 'mail', 'webmail', 'smtp', 'ftp', 'cpanel', 'autodiscover', 'localhost', 'test', 'staging', 'dev', 'support', 'assets', 'cdn', 'login', 'settings', 'lgus', 'demo'];

    public static function domain(): ?string
    {
        $domain = strtolower(trim((string) config('lgu_websites.domain')));
        if ($domain === '') {
            return null;
        }
        if (strlen($domain) > 189 || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain)) {
            throw new InvalidArgumentException('LGU_WEBSITE_DOMAIN must be a hostname without a scheme, path or wildcard.');
        }
        if (! self::applicationHost()) {
            throw new InvalidArgumentException('APP_URL must include the primary application hostname.');
        }

        return $domain;
    }

    public static function applicationHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public static function reservedSlugs(): array
    {
        return array_unique([...self::RESERVED, explode('.', self::applicationHost())[0]]);
    }

    public static function centralUrl(string $route, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($route, $parameters, false);
    }

    public static function publicUrl(MunicipalityWebsite $website): string
    {
        return self::domain() ? 'https://'.$website->slug.'.'.self::domain().'/'
            : self::centralUrl('lgu.show', ['slug' => $website->slug]);
    }

    public static function loginUrl(MunicipalityWebsite $website): string
    {
        return self::domain() ? self::publicUrl($website).'login'
            : self::centralUrl('lgu.login', ['slug' => $website->slug]);
    }
}
