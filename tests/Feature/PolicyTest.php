<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Policies\ImportPolicy;
use App\Policies\ReportPolicy;
use App\Policies\ReportTemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_policy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = Import::factory()->for($owner)->create();
        $policy = new ImportPolicy;

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->create($owner));

        $this->assertTrue($policy->view($owner, $import));
        $this->assertFalse($policy->view($other, $import));

        $this->assertTrue($policy->update($owner, $import));
        $this->assertFalse($policy->update($other, $import));

        $this->assertTrue($policy->delete($owner, $import));
        $this->assertFalse($policy->delete($other, $import));
    }

    public function test_report_policy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = Import::factory()->for($owner)->create();
        $report = Report::factory()->for($owner)->for($import)->create();
        $policy = new ReportPolicy;

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->create($owner));

        $this->assertTrue($policy->view($owner, $report));
        $this->assertFalse($policy->view($other, $report));

        $this->assertTrue($policy->update($owner, $report));
        $this->assertFalse($policy->update($other, $report));

        $this->assertTrue($policy->delete($owner, $report));
        $this->assertFalse($policy->delete($other, $report));
    }

    public function test_report_template_policy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $template = ReportTemplate::factory()->for($owner)->create();
        $policy = new ReportTemplatePolicy;

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->create($owner));

        $this->assertTrue($policy->view($owner, $template));
        $this->assertFalse($policy->view($other, $template));

        $this->assertTrue($policy->update($owner, $template));
        $this->assertFalse($policy->update($other, $template));

        $this->assertTrue($policy->delete($owner, $template));
        $this->assertFalse($policy->delete($other, $template));
    }

    public function test_gate_terikat_ke_policy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = Import::factory()->for($owner)->create();

        $this->assertTrue(\Gate::forUser($owner)->allows('view', $import));
        $this->assertFalse(\Gate::forUser($other)->allows('view', $import));
    }
}
