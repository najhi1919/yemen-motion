<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontendDesignerProfileFeaturedWorksSourceTest extends TestCase
{
    public function test_owner_composable_uses_official_contract_and_refreshes_conflicts(): void
    {
        $source = $this->source(
            'frontend/composables/'
            .'useDesignerProfileFeaturedWorks.ts',
        );

        $this->assertStringContainsString(
            "'/designer/profile/featured-works'",
            $source,
        );
        $this->assertStringContainsString(
            "method: 'PUT'",
            $source,
        );
        $this->assertStringContainsString(
            'expected_updated_at:',
            $source,
        );
        $this->assertStringContainsString(
            'designer_profile_version_conflict',
            $source,
        );
        $this->assertStringContainsString(
            'await fetchFeaturedWorks()',
            $source,
        );
        $this->assertStringContainsString(
            'const response = await fetch(requestUrl, {',
            $source,
        );
        $this->assertStringContainsString(
            'const blob = await response.blob()',
            $source,
        );
        $this->assertStringContainsString(
            "'Authorization'",
            $source,
        );
        $this->assertStringNotContainsString(
            "responseType: 'blob'",
            $source,
        );
    }

    public function test_owner_page_integrates_panel_drawer_and_version_refreshes(): void
    {
        $page = $this->source(
            'frontend/pages/designer/index.vue',
        );

        $this->assertStringContainsString(
            'DesignerProfileFeaturedWorksPanel',
            $page,
        );
        $this->assertStringContainsString(
            'DesignerProfileFeaturedWorksDrawer',
            $page,
        );
        $this->assertStringContainsString(
            'fetchFeaturedWorks',
            $page,
        );
        $this->assertStringContainsString(
            'handleFeaturedWorksSave',
            $page,
        );
        $this->assertStringContainsString(
            'disposeFeaturedWorksCoverUrls',
            $page,
        );
        $this->assertStringContainsString(
            ':cover-urls="featuredWorksCoverUrls"',
            $page,
        );
    }

    public function test_drawer_is_keyboard_accessible_and_uses_button_ordering(): void
    {
        $drawer = $this->source(
            'frontend/components/designer/profile/'
            .'DesignerProfileFeaturedWorksDrawer.vue',
        );

        $this->assertStringContainsString(
            'role="dialog"',
            $drawer,
        );
        $this->assertStringContainsString(
            'aria-modal="true"',
            $drawer,
        );
        $this->assertStringContainsString(
            "event.key === 'Escape'",
            $drawer,
        );
        $this->assertStringContainsString(
            "event.key !== 'Tab'",
            $drawer,
        );
        $this->assertStringContainsString(
            'تحريك ${work.title} للأعلى',
            $drawer,
        );
        $this->assertStringContainsString(
            'تحريك ${work.title} للأسفل',
            $drawer,
        );
        $this->assertStringContainsString(
            'window.confirm(',
            $drawer,
        );
        $this->assertStringContainsString(
            '() => props.state?.expected_updated_at',
            $drawer,
        );
        $this->assertStringContainsString(
            'if (props.open) {',
            $drawer,
        );
        $this->assertStringNotContainsString(
            'draggable=',
            $drawer,
        );
    }

    public function test_public_profile_renders_featured_before_regular_works(): void
    {
        $types = $this->source(
            'frontend/types/public-designer-profile.ts',
        );
        $page = $this->source(
            'frontend/pages/designers/[username].vue',
        );
        $grid = $this->source(
            'frontend/components/public/designer/'
            .'PublicDesignerWorksGrid.vue',
        );

        $this->assertStringContainsString(
            'featured_works: {',
            $types,
        );
        $this->assertStringContainsString(
            'profile.featured_works.items',
            $page,
        );
        $this->assertStringContainsString(
            'profile.featured_works.total',
            $page,
        );
        $this->assertStringContainsString(
            'profile.works.items',
            $page,
        );
        $this->assertStringContainsString(
            'profile.works.total > 0',
            $page,
        );
        $this->assertStringContainsString(
            'profile.featured_works.total === 0',
            $page,
        );

        $featuredPosition = strpos(
            $page,
            'profile.featured_works.items',
        );
        $regularPosition = strpos(
            $page,
            'profile.works.items',
        );

        $this->assertIsInt($featuredPosition);
        $this->assertIsInt($regularPosition);
        $this->assertLessThan(
            $regularPosition,
            $featuredPosition,
        );

        $this->assertStringContainsString(
            "props.featured ? 'featured-works' : 'works'",
            $grid,
        );
        $this->assertStringContainsString(
            "'public-works-feed--featured'",
            $grid,
        );
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/'.$relativePath,
        );

        $this->assertIsString($source);

        return $source;
    }
}
