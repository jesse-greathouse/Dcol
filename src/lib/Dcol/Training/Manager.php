<?php

namespace Dcol\Training;

use Illuminate\Http\Client\Response;
use Illuminate\Database\Eloquent\Collection;

use Dcol\AbstractManager,
    Dcol\Content\Manager as ContentManager;

use App\Models\BlogPost,
    App\Models\Content;

class Manager extends AbstractManager
{
    
    /**
     * Constructor.
     *
     * @param ChatCompletion $chat
     * @param string $cacheDir
     * @param string $tmpDir
     * @param string $uri
     */
    public function __construct(ChatCompletion $chat, string $baseCacheDir, string $baseTmpDir)
    {
        $this->setChat($chat);
        $this->setUri($uri);
        $this->setCacheDir($baseCacheDir);
        $this->setTmpDir($baseTmpDir);
        $this->setFileExtension(Manager::FILE_EXT);
    }

    public function makeTrainingLines(Collection $blogPosts)
    {
        $lines = [];
    }

    protected function makeLine(string $system, string $user, string $assistant, string $contentType)
    {

    }

    public function createTrainingFile(array $lines, string $suffix ) 
    {
        $job = '';

        foreach($lines as $line) {
            ['system' => $system, 'user' => $user, 'assistant' => $assistant] = $line;

            $row = [
                'messages' => [
                    'role'      => 'system',
                    'content'   => $system,
                ],
                [
                    'role'      => 'user',
                    'content'   => $user,
                ],
                [
                    'role'      => 'assistant',
                    'content'   => $assistant,
                ],
            ];

            $job .= json_encode($row) . "\n";

        }

        var_dump($job); die();

        $this->setCache($job, "$suffix.jsonl");
    }

    /**
     * Get openAI chatcompletion implementation.
     *
     * @return  ChatCompletion
     */ 
    public function getChat(): ChatCompletion
    {
        return $this->chat;
    }

    /**
     * Set openAI chatcompletion implementation.
     *
     * @param  ChatCompletion  $chat  OpenAI chatcompletion implementation.
     *
     * @return  self
     */ 
    public function setChat(ChatCompletion $chat): Manager
    {
        $this->chat = $chat;

        return $this;
    }

}
