<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class DropzoneErrorResponse extends JsonResponse
{
    public function __construct(string $message, int $status = self::HTTP_BAD_REQUEST, array $headers = [], bool $json = false)
    {
        $data = [
            'error' => $message,
        ];

        parent::__construct($data, $status, $headers, $json);
    }
}
