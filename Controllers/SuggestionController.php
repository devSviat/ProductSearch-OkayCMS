<?php

namespace Okay\Modules\Sviat\ProductSearch\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Modules\Sviat\ProductSearch\Services\SuggestionProvider;

class SuggestionController extends AbstractController
{
    public function lookup(SuggestionProvider $provider)
    {
        $query = trim((string) $this->request->get('query', null, null, false));
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        if ($query === '') {
            $this->response->setContent(json_encode((object) [
                'query' => '',
                'suggestions' => [],
            ], $flags), RESPONSE_JSON);

            return;
        }

        $payload = $provider->buildResponse($query);
        $this->response->setContent(json_encode($payload, $flags), RESPONSE_JSON);
    }
}
