<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api\Data;

interface SessionInterface
{
    const ENTITY_ID = 'entity_id';
    const BUYER_COOKIE = 'buyer_cookie';
    const CLIENT_TYPE = 'client_type';
    const CORP_ADDRESS_ID = 'corp_address_id';
    const PARTNER_IDENTITY = 'partner_identity';
    const TEMPPO = 'temppo';
    const ERP_ORDER_NUMBER = 'erp_order_number';
    const FULL_NAME = 'full_name';
    const FIRST_NAME = 'first_name';
    const LAST_NAME = 'last_name';
    const PHONE = 'phone';
    const ADDRESS_ID = 'address_id';
    const BROWSER_FORM_POST_URL = 'browser_form_post_url';
    const CUSTOMER_ID = 'customer_id';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // Status constants
    const STATUS_NEW = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_ERROR = 3;
}
