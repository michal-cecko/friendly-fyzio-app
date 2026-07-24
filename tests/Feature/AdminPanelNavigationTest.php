<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Clusters\System\Pages\WebSettings;
use App\Filament\Clusters\System\SystemCluster;
use Filament\Facades\Filament;
use Tests\TestCase;

class AdminPanelNavigationTest extends TestCase
{
    public function test_clustered_components_are_registered_exactly_once(): void
    {
        // discoverClusters() already discovers pages/resources inside the clusters
        // directory; a redundant discoverPages()/discoverResources() over the same
        // directory used to double every cluster's sub-navigation.
        foreach (Filament::getPanel('admin')->getClusteredComponents() as $cluster => $components) {
            $this->assertSame(
                array_values(array_unique($components)),
                array_values($components),
                "Cluster {$cluster} registers duplicate sub-navigation components.",
            );
        }
    }

    public function test_cluster_pages_and_resources_are_still_discovered(): void
    {
        // Users management lives in Provoz (next to Klienti); Nastavení keeps its
        // own pages/resources such as WebSettings.
        $provozComponents = Filament::getPanel('admin')->getClusteredComponents(ProvozCluster::class);
        $systemComponents = Filament::getPanel('admin')->getClusteredComponents(SystemCluster::class);

        $this->assertContains(UserResource::class, $provozComponents);
        $this->assertContains(WebSettings::class, $systemComponents);
    }
}
