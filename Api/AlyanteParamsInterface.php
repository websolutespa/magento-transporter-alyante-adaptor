<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterAlyanteAdaptor\Api;

interface AlyanteParamsInterface
{
    /**
     * @return string
     */
    public function getMethod(): string;

    /**
     * @return string
     */
    public function getResourceName(): string;

    /**
     * @return string
     */
    public function getBody(): string;

    /**
     * @return string
     */
    public function getUrlParams(): string;
}
