<?php

declare(strict_types=1);

namespace Rehla\Content\Data;

use Rehla\Content\Exceptions\ContentValidationFailed;

final readonly class PageInputData
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $body
     * @param  array{en: string, ar: string}  $metaTitle
     * @param  array{en: string, ar: string}  $metaDescription
     */
    public function __construct(
        public string $slug,
        public array $title,
        public array $body,
        public array $metaTitle,
        public array $metaDescription,
    ) {
        $errors = [];
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 160) {
            $errors['slug'][] = 'content.slug_invalid';
        }
        foreach (['en', 'ar'] as $locale) {
            $title = trim($this->title[$locale]);
            if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
                $errors['title_'.$locale][] = 'content.title_invalid';
            }
            if (trim($this->body[$locale]) === '') {
                $errors['body_'.$locale][] = 'content.body_required';
            }
            if (mb_strlen(trim($this->metaTitle[$locale])) > 150) {
                $errors['meta_title_'.$locale][] = 'content.meta_title_too_long';
            }
            if (mb_strlen(trim($this->metaDescription[$locale])) > 320) {
                $errors['meta_description_'.$locale][] = 'content.meta_description_too_long';
            }
        }
        if ($errors !== []) {
            throw new ContentValidationFailed($errors);
        }
    }
}
