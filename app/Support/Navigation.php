<?php

namespace App\Support;

use App\Models\User;

/** Menu sidebar dari config/navigation.php, difilter izin akun. */
class Navigation
{
    public static function for(User $user): array
    {
        return collect(config('navigation'))
            ->map(function (array $section) use ($user) {
                $section['items'] = self::filter($section['items'], $user);

                return $section;
            })
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    /** Menu diratakan untuk pencarian sidebar: [{slug, title}]. */
    public static function flat(User $user): array
    {
        $out = [];
        $walk = function (array $items) use (&$walk, &$out) {
            foreach ($items as $item) {
                isset($item['children']) ? $walk($item['children']) : $out[] = ['slug' => $item['slug'], 'title' => $item['title']];
            }
        };

        foreach (self::for($user) as $section) {
            $walk($section['items']);
        }

        return $out;
    }

    private static function filter(array $items, User $user): array
    {
        return collect($items)
            ->map(function (array $item) use ($user) {
                if (isset($item['children'])) {
                    $item['children'] = self::filter($item['children'], $user);

                    return $item['children'] === [] ? null : $item;
                }

                return ! isset($item['permission']) || $user->can($item['permission']) ? collect($item)->except('permission')->all() : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
