<?php

namespace Tests\Unit;

use App\Models\Admin\Article;
use Tests\TestCase;

class ArticleMeaningfulContentTest extends TestCase
{
    public function test_empty_ckeditor_html_is_not_meaningful(): void
    {
        $this->assertFalse(Article::hasMeaningfulContent(null));
        $this->assertFalse(Article::hasMeaningfulContent(''));
        $this->assertFalse(Article::hasMeaningfulContent('   '));
        $this->assertFalse(Article::hasMeaningfulContent('<p></p>'));
        $this->assertFalse(Article::hasMeaningfulContent('<p>&nbsp;</p>'));
        $this->assertFalse(Article::hasMeaningfulContent('<p><br></p>'));
    }

    public function test_real_article_html_is_meaningful(): void
    {
        $this->assertTrue(Article::hasMeaningfulContent('<p>How to play GTA6 with more accurately</p>'));
    }
}
