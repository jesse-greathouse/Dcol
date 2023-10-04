<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request,
    Illuminate\Support\Facades\Gate;

use App\Models\AiModel,
    App\Http\Resources\AiModel as AiModelResource,
    App\Http\Resources\AiModelCollection,
    App\Http\Requests\StoreAiModelRequest;

class AiModelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AiModelCollection
    {
        $user = $request->user();
        $aiModels = getAiModelaQb()->get();
        return AiModelCollection::make($blogs);
    }

    /**
     * Returns a querybuilder with the eligible blog posts query.
     *
     * @return Builder
     */
    protected function getAiModelaQb(): Builder
    {
        $user = $request->user();
        $qb = AiModel::join('ai_training_files', 'ai_models.ai_training_file_id', '=', 'ai_training_files.id')
            ->join('blogs', 'ai_training_files.blog_id', '=', 'blogs.id')
            ->where('ai_models.active', 1)
            ->where('blogs.user_id', $user->id);

        return $qb;
    }
}
