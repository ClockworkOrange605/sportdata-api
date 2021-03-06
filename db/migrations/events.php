<?php 
    use Illuminate\Database\Capsule\Manager as Capsule;

    Capsule::schema()->create('events', function ($table) {
        $table->id();
        $table->foreignId('league_id')->constrained('leagues');
        $table->foreignId('home_team_id')->constrained('teams');
        $table->foreignId('away_team_id')->constrained('teams');
        $table->string('name');
        $table->string('status');
        $table->dateTime('date');
        $table->integer('home_score')->nullable();
        $table->integer('away_score')->nullable();        

        $table->unique(['league_id', 'name', 'date']);
    });

    Capsule::schema()->create('event_odds', function ($table) {
        $table->id();
        $table->foreignId('type_id')->constrained('odds');
        $table->foreignId('event_id')->constrained('events');
        $table->string('type');
        $table->string('period_name')->nullable();
        $table->string('period_time')->nullable();
        $table->string('name');
        $table->float('value');
        $table->float('condition')->nullable();
        // $table->boolean('is_win')->nullable();
    });
