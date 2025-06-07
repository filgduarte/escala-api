<?php
require_once __DIR__ . '/../core/AbstractCrudController.php';

class InstrumentController extends AbstractCrudController
{
    protected static string $table = 'instruments';
    protected static array $fields = ['name', 'essential', 'combinable', 'exclusive'];
}
