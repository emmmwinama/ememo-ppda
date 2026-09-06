<?php
/** Reference-data entity definitions, shared by admin_refdata*.php. */

function es_refdata_defs(): array
{
    return [
        'pde' => [
            'label'  => 'PDEs',
            'table'  => 'es_pde',
            'key'    => 'id',
            'fields' => [
                'name'     => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'ref_code' => ['label' => 'Reference code', 'type' => 'text'],
                'email'    => ['label' => 'Email', 'type' => 'email'],
                'address'  => ['label' => 'Address', 'type' => 'text'],
            ],
            'list'   => ['name', 'ref_code', 'email'],
        ],
        'method' => [
            'label'  => 'Procurement methods',
            'table'  => 'es_procurement_method',
            'key'    => 'id',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'code' => ['label' => 'Code', 'type' => 'text'],
            ],
            'list'   => ['name', 'code'],
        ],
        'review_type' => [
            'label'  => 'Review types',
            'table'  => 'es_review_type',
            'key'    => 'id',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            ],
            'list'   => ['name'],
        ],
        'category' => [
            'label'  => 'Supplier categories',
            'table'  => 'es_category',
            'key'    => 'id',
            'fields' => [
                'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['goods', 'services', 'works'], 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'code' => ['label' => 'Code', 'type' => 'text'],
                'fee'  => ['label' => 'Registration fee (MWK)', 'type' => 'number'],
            ],
            'list'   => ['type', 'name', 'code', 'fee'],
        ],
        'currency' => [
            'label'  => 'Currencies',
            'table'  => 'es_currency',
            'key'    => 'code',
            'fields' => [
                'code' => ['label' => 'ISO code', 'type' => 'text', 'required' => true, 'maxlength' => 3, 'upper' => true, 'lock_on_edit' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            ],
            'list'   => ['code', 'name'],
        ],
        'country' => [
            'label'  => 'Countries',
            'table'  => 'es_country',
            'key'    => 'id',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'iso2' => ['label' => 'ISO2', 'type' => 'text', 'maxlength' => 2, 'upper' => true],
            ],
            'list'   => ['name', 'iso2'],
        ],
    ];
}
