<?php 
//根据 Annotation 自动生成的路由规则
Route::post('account/login','index/Account/account_login');
Route::get('account/info','index/Account/account_info');
Route::post('account/edit','index/Account/edit_profile');
Route::post('account/passwd','index/Account/change_password');
Route::post('account/register','index/Account/account_register');
Route::get('account/logout','index/Account/logout');
Route::rule('index','index/Index/index');
Route::rule('connectivity','index/Index/connectivity_test');
Route::get('ticket/list','index/Order/orders_info');
Route::get('ticket/id/:order_id','index/Order/order_info');
Route::post('ticket/order','index/Order/order_ticket');
Route::post('ticket/change','index/Order/change_travel');
Route::post('ticket/refund','index/Order/delete_order');
Route::rule('ticket/remain','index/Order/remaining_ticket');
Route::rule('train/pages','index/Train/get_pages');
Route::get('train/info/:page','index/Train/trains_info');
Route::get('train/id/:id','index/Train/train_info');
Route::post('train/edit','index/Train/edit_train');
Route::post('train/station/edit','index/Train/edit_train_info');
Route::rule('train/routine/:start/:end','index/Train/get_trip');
Route::post('edit/train','index/Train/set_train');