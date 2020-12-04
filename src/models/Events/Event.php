<?php
    namespace SportData\Models\Events;

    use Illuminate\Database\Eloquent\Model;

    class Event extends Model
    {
        protected $fillable = ['league_id', 'home_team_id', 'away_team_id',
            'name', 'status', 'home_score', 'away_score', 'start_at'];

        public $timestamps = false;

        public function league()
        {
            return $this->belongsTo('SportData\Models\Common\League');
        }

        // public function homeTeam()
        // {
        //     return $this->hasOne(\SportData\Models\Common\Team::class, '');
        // }

        // public function awayTeam()
        // {
        //     return $this->hasOne(\SportData\Models\Common\Team::class, '');
        // }

        public function sources()
        {
            return $this->morphToMany('SportData\Models\Source', 
                'sources_pivot', null, 'entity_id', 'source_id'
            )->withPivot('external_id');
        }
    }

?>