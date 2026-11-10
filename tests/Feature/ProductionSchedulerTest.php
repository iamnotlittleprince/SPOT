<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionSchedulerTest extends TestCase
{
    public function test_scheduled_tasks_run_inside_php_without_spawning_an_external_php_binary(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $events = collect(app(Schedule::class)->events());
        foreach (['audit:prune', 'inbox:prune', 'notifications:dispatch'] as $name) {
            $event = $events->first(fn ($event) => $event->description === $name);
            $this->assertInstanceOf(CallbackEvent::class, $event);
            $this->assertTrue($event->withoutOverlapping);
            Artisan::shouldReceive('call')->with($name)->once()->andReturn(0);
            $event->run($this->app);
        }
    }

    public function test_scheduler_marks_a_failed_command_as_failed(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'notifications:dispatch');
        Artisan::shouldReceive('call')->with('notifications:dispatch')->once()->andReturn(1);
        $event->run($this->app);
        $this->assertSame(1, $event->exitCode);
    }

}
