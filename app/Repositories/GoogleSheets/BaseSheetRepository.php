<?php

namespace App\Repositories\GoogleSheets;

use App\Repositories\MySql\BaseMySqlRepository;

/**
 * Backwards-compatible class name for repository contracts that pre-date the
 * MySQL cutover. Active runtime persistence is implemented entirely by
 * BaseMySqlRepository; this class never creates or calls a Google client.
 */
abstract class BaseSheetRepository extends BaseMySqlRepository {}
