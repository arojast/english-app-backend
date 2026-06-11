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

            $response = Http::get("https://api.dictionaryapi.dev/api/v2/entries/en/{$wordText}");

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Word not found in dictionary'
                ], 404);
            }

            $data = $response->json();

            $meaning = $data[0]['meanings'][0]['definitions'][0]['definition'] ?? null;
            $pronunciation = $data[0]['phonetic'] ?? ($data[0]['phonetics'][0]['text'] ?? $data[0]['phonetics'][1]['text']);
            $audio = $data[0]['phonetics'][0]['audio'] ?? $data[0]['phonetics'][1]['audio'];
            $partOfSpeech = $data[0]['meanings'][0]['partOfSpeech'] ?? null;
            $synonyms = $data[0]['meanings'][0]['definitions'][0]['synonyms'] ?? [];
            $antonyms = $data[0]['meanings'][0]['definitions'][0]['antonyms'] ?? [];

            //service translate word
            $tr = new GoogleTranslate('es');
            $translate = $tr->translate($wordText);

            $word = Word::create([
                'word' => $wordText,
                'meaning' => $meaning,
                'translate' => $translate,
                'pronunciation' => $pronunciation,
                'audio_url' => $audio,
                'synonyms' => json_encode($synonyms),
                'antonyms' => json_encode($antonyms),
            ]);
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
