<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Word;

//project translate words
use Stichoza\GoogleTranslate\GoogleTranslate;


class WordController extends Controller
{
    protected function getWordAPI($word) 
    {
        $response = Http::get("https://api.dictionaryapi.dev/api/v2/entries/en/{$word}");
        $data = [];

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Word not found in dictionary'
            ], 404);
        }

        $response = $response->json();
        
        $data['word'] = $word;
        $data['meaning'] = $response[0]['meanings'][0]['definitions'][0]['definition'] ?? null;
        $data['pronunciation'] = $response[0]['phonetic'] ?? ($response[0]['phonetics'][0]['text'] ?? $response[0]['phonetics'][1]['text']);

        if(isset($response[0]['phonetics'][0]['audio']) && !empty($response[0]['phonetics'][0]['audio'])) {
            $data['audio_url'] = $response[0]['phonetics'][0]['audio'];
        } else if(isset($response[0]['phonetics'][1]['audio']) && !empty($response[0]['phonetics'][1]['audio'])) {
            $data['audio_url'] = $response[0]['phonetics'][1]['audio'];
        } else if(isset($response[0]['phonetics'][2]['audio']) && !empty($response[0]['phonetics'][2]['audio'])) {
            $data['audio_url'] = $response[0]['phonetics'][2]['audio'];
        } else {
            $data['audio_url'] = null;
        }
        
        $data['partOfSpeech'] = $response[0]['meanings'][0]['partOfSpeech'] ?? null;
        $data['synonyms'] = [];
        $data['antonyms'] = [];

        // Extract synonyms and antonyms from the API response
        $synonyms = $response[0]['meanings'][0]['definitions'][0]['synonyms'] ?? [];
        $antonyms = $response[0]['meanings'][0]['definitions'][0]['antonyms'] ?? [];

        if(isset($response[0]['meanings'][0]['synonyms'])) {
            $synonyms = array_merge($synonyms, $response[0]['meanings'][0]['synonyms']);
        }

        if(isset($response[0]['meanings'][1]['synonyms'])) {
            $synonyms = array_merge($synonyms, $response[0]['meanings'][1]['synonyms']);
        }
        
        // Remove duplicates
        $synonyms = array_unique($synonyms);

        // Clean structure of array_unique
        if(!empty($synonyms)) {
            foreach ($synonyms as $synonym) {
                $data['synonyms'][] = $synonym;
            }
        }
        
        if(isset($response[0]['meanings'][0]['antonyms'])) {
            $antonyms = array_merge($antonyms, $response[0]['meanings'][0]['antonyms']);
        }

        if(isset($response[0]['meanings'][1]['antonyms'])) {
            $antonyms = array_merge($antonyms, $response[0]['meanings'][1]['antonyms']);
        }

        // Remove duplicates
        $antonyms = array_unique($antonyms);

        // Clean structure of array_unique
        if(!empty($antonyms)) {
            foreach ($antonyms as $antonym) {
                $data['antonyms'][] = $antonym;
            }
        }

        return $data;
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'word' => 'required|string'
        ]);

        $wordText = strtolower($request->word);

        // Check if word exists
        $word = Word::where('word', $wordText)->first();

        // If not exists → call API
        if (!$word) {

            $data = $this->getWordAPI($wordText);

            //service translate word
            $tr = new GoogleTranslate('es');
            $data['translate'] = $tr->translate($wordText);

            $word = Word::create($data);
        }

        // Attach to user (no duplicates)
        $request->user()->words()->syncWithoutDetaching([$word->id]);

        return response()->json([
            'message' => 'Word saved successfully',
            'word' => $word
        ]);
    }

    public function updateLearned(Request $request, $id)
    {
        $request->validate([
            'is_learned' => 'required|boolean'
        ]);

        $word = $request->user()->words()->find($id);

        if ($word === NULL || !$request->user()->words()->where('word_id', $word->id)->exists()) {
            return response()->json([
                'message' => 'Word not associated with user'
            ], 404);
        }

        $request->user()->words()->updateExistingPivot($word->id, ['is_learned' => $request->is_learned]);        

        return response()->json([
            'message' => 'Word learning status updated',
            'word' => $word,
            'is_learned' => $request->is_learned
        ]);
    }

    public function updateFavorite(Request $request, $id)
    {
        $request->validate([
            'is_favorite' => 'required|boolean'
        ]);

        $word = $request->user()->words()->find($id);
        
        if ($word === NULL || !$request->user()->words()->where('word_id', $word->id)->exists()) {
            return response()->json([
                'message' => 'Word not associated with user'
            ], 404);
        }

        $request->user()->words()->updateExistingPivot($word->id, ['is_favorite' => $request->is_favorite]);        

        return response()->json([
            'message' => 'Word favorite status updated',
            'word' => $word,
            'is_favorite' => $request->is_favorite
        ]);
    }

    public function last(Request $request)
    {
        $words = $request->user()
            ->words()
            ->orderByPivot('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json($words);
    }

    public function findAll(Request $request)
    {
        $search = $request->query('search', '');
        $words = $request->user()
            ->words()
            ->where('word', 'like', '%' . $search . '%')
            ->orderBy('word', 'asc')
            ->paginate(10);

        return $words;

    }

    public function randomUser(Request $request)
    {
        $word = $request->user()
            ->words()
            ->inRandomOrder()
            ->first();

        if (!$word) {
            return response()->json([
                'message' => 'No words found'
            ], 404);
        }

        return response()->json($word);
    }
}
