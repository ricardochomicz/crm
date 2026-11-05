<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Prepaid = 'PREPAID';
    case Control = 'CONTROL';
    case Postpaid = 'POSTPAID';
    case Broadband = 'BROADBAND';
    case BusinessInternet = 'BUSINESS_INTERNET';
    case Landline = 'LANDLINE';
    case PayTv = 'PAY_TV';
    case Bundle = 'BUNDLE';
    case AddOn = 'ADD_ON';
    case Other = 'OTHER';
}
