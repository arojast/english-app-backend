<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'word',
        'meaning',
        'translate',
        'pronunciation',
        'audio_url',
        'synonyms',
        'antonyms',
        
    ];

    public function users()
    {
        return $this->belongsToMany(User::class,'word_user')->withTimestamps();
    }
}
