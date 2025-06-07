<?php

return [
    'secret'     => getenv('JWT_SECRET'),
    'issuer'     => getenv('JWT_ISSUER'),
    'expiration' => getenv('JWT_EXPIRATION') ?: 3600
];