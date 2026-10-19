<?php

namespace App\Modules\Assistant\Application;

/** Presentation choices only: every guide uses the same authorization and chat. */
final class AssistantGuide
{
    public const DEFAULT = 'ka-berto';

    private const CHARACTERS = [
        'ka-berto' => ['name' => 'Ka Berto', 'alt' => 'Ka Berto, a friendly brown cow farmer wearing a salakot'],
        'ka-kaloy' => ['name' => 'Ka Kaloy', 'alt' => 'Ka Kaloy, a friendly carabao farmer wearing a salakot'],
        'aling-maya' => ['name' => 'Aling Maya', 'alt' => 'Aling Maya, a Filipina farmer with a basket of vegetables'],
        'kiko' => ['name' => 'Kiko', 'alt' => 'Kiko, a young Filipino farm guide holding a clipboard'],
    ];

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::CHARACTERS);
    }

    /** Regex fragment only; callers supply anchors, boundaries and modifiers. */
    public static function namePattern(): string
    {
        $names = array_map(fn (array $guide) => str_replace(' ', '\\s+', preg_quote($guide['name'], '/')), self::CHARACTERS);

        return '(?:'.implode('|', $names).')';
    }

    /** A complete catalog name, never a substring, ID or arbitrary addressee. */
    public static function idForName(string $name): ?string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\\s+/u', ' ', $name) ?? ''), 'UTF-8');
        foreach (self::CHARACTERS as $id => $guide) {
            if ($normalized === mb_strtolower($guide['name'], 'UTF-8')) {
                return $id;
            }
        }

        return null;
    }

    /** @return list<array{id:string,name:string,alt:string,avatar:string,portrait:string}> */
    public static function all(): array
    {
        return array_map(self::get(...), self::ids());
    }

    /** @return array{id:string,name:string,alt:string,avatar:string,portrait:string} */
    public static function get(string $id = self::DEFAULT): array
    {
        $id = array_key_exists($id, self::CHARACTERS) ? $id : self::DEFAULT;

        return ['id' => $id] + self::CHARACTERS[$id] + [
            'avatar' => 'images/mascots/'.$id.'-avatar-v1.webp',
            'portrait' => 'images/mascots/'.$id.'-portrait-v1.webp',
        ];
    }
}
