<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api\Data;

interface ItemInterface
{
    const ENTITY_ID = 'entity_id';
    const SESSION_ID = 'session_id';
    const PRODUCT_ID = 'product_id';
    const QTY = 'qty';
    const PRICE = 'price';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
