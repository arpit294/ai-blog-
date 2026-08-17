<?php

namespace Tests\Feature;

use App\Models\AutomationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationProfileTest extends TestCase
{
    use RefreshDatabase;

    private function validProfileData()
    {
        return [
            'name' => 'Test Profile',
            'niche' => 'Tech',
            'target_audience' => 'Devs',
            'language' => 'English',
            'tone' => 'Professional',
            'min_words' => 1000,
            'max_words' => 2000,
            'quota_count' => 1,
            'quota_period' => 'daily',
            'generate_seo' => true,
            'generate_image' => false,
            'duplicate_mode' => 'strict',
            'duplicate_threshold' => 0.85,
            'publish_mode' => 'draft',
            'status' => 'active',
        ];
    }

    public function test_automation_profile_can_be_created()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->post(route('automation-profiles.store'), $this->validProfileData());
        
        $response->assertRedirect(route('automation-profiles.index'));
        $this->assertDatabaseHas('automation_profiles', ['name' => 'Test Profile']);
    }

    public function test_automation_profile_can_be_updated()
    {
        $user = User::factory()->create();
        $profile = AutomationProfile::create(array_merge($this->validProfileData(), ['user_id' => $user->id]));
        
        $data = $this->validProfileData();
        $data['name'] = 'Updated Profile';

        $response = $this->actingAs($user)->put(route('automation-profiles.update', $profile), $data);
        
        $response->assertRedirect(route('automation-profiles.index'));
        $this->assertDatabaseHas('automation_profiles', ['name' => 'Updated Profile']);
    }

    public function test_automation_profile_can_be_deleted_safely()
    {
        $user = User::factory()->create();
        $profile = AutomationProfile::create(array_merge($this->validProfileData(), ['user_id' => $user->id]));
        
        $response = $this->actingAs($user)->delete(route('automation-profiles.destroy', $profile));
        
        $response->assertRedirect(route('automation-profiles.index'));
        $this->assertDatabaseMissing('automation_profiles', ['id' => $profile->id]);
    }

    public function test_automation_profile_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $profile = AutomationProfile::create(array_merge($this->validProfileData(), ['user_id' => $user->id]));
        
        $this->assertTrue($profile->user->is($user));
    }

    public function test_automation_profile_has_schedules()
    {
        $user = User::factory()->create();
        $profile = AutomationProfile::create(array_merge($this->validProfileData(), ['user_id' => $user->id]));
        
        $profile->schedules()->create([
            'weekday' => 'Monday',
            'time' => '10:00:00',
        ]);
        
        $this->assertCount(1, $profile->schedules);
        $this->assertEquals('Monday', $profile->schedules->first()->weekday);
    }

    public function test_max_words_cannot_be_lower_than_min_words()
    {
        $user = User::factory()->create();
        $data = $this->validProfileData();
        $data['min_words'] = 2000;
        $data['max_words'] = 1000; // Invalid
        
        $response = $this->actingAs($user)->post(route('automation-profiles.store'), $data);
        
        $response->assertSessionHasErrors('max_words');
    }

    public function test_duplicate_threshold_must_be_between_0_and_1()
    {
        $user = User::factory()->create();
        
        // Test > 1
        $data = $this->validProfileData();
        $data['duplicate_threshold'] = 1.5;
        $response = $this->actingAs($user)->post(route('automation-profiles.store'), $data);
        $response->assertSessionHasErrors('duplicate_threshold');

        // Test < 0
        $data['duplicate_threshold'] = -0.5;
        $response = $this->actingAs($user)->post(route('automation-profiles.store'), $data);
        $response->assertSessionHasErrors('duplicate_threshold');
    }

    public function test_invalid_publishing_mode_is_rejected()
    {
        $user = User::factory()->create();
        $data = $this->validProfileData();
        $data['publish_mode'] = 'invalid_mode';
        
        $response = $this->actingAs($user)->post(route('automation-profiles.store'), $data);
        
        $response->assertSessionHasErrors('publish_mode');
    }
}
