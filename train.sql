# Create database
create schema if not exists train;

# Switch to the database
use train;

/**
 * Tables
 */

# train table
create table if not exists train
(
	train_id      varchar(10) not null
		primary key,
	depart        varchar(40) null,
	arrival       varchar(40) null,
	station_count int         null,
	seat_count    int         null
);


# train_station table
create table if not exists train_station
(
	train_id      varchar(10) not null,
	station_order int         null,
	station       varchar(40) null,
	arrival_time  time        null,
	depart_time   time        null,
	constraint train_station_train_train_id_fk
	foreign key (train_id) references train (train_id)
		on update cascade
		on delete cascade
);

# train_station indices
create index train_station_arrival_time_index
	on train_station (arrival_time);

create index train_station_depart_time_index
	on train_station (depart_time);

create index train_station_station_index
	on train_station (station);


# users table
create table if not exists users
(
	id          int auto_increment
		primary key,
	user_id     varchar(255) charset latin1 not null,
	passwd      char(32)                    not null,
	role        int default '0'             not null
	comment '0 user, 1 admin',
	name        varchar(10)                 null,
	sex         int default '0'             not null
	comment '0 unknown, 1 male, 2 female
',
	birthday    date                        null,
	id_number   char(18)                    null,
	phone       char(11)                    null,
	ticket_type int default '0'             not null,
	
	# table constraints
	constraint users_user_id_uindex
	unique (user_id)
);


# orders table
create table if not exists orders
(
	order_id       int auto_increment
		primary key,
	user_id        int                                 not null,
	create_date    timestamp default CURRENT_TIMESTAMP not null,
	depart_date    date                                null,
	status         int default '0'                     not null
	comment '-1 canceled, 0 waiting for payment, 1 payed',
	train_id       varchar(10)                         not null,
	depart_arrival int                                 not null,
	price          int default '0'                     not null,
	
	# table constraints
	constraint orders_train_train_id_fk
	foreign key (train_id) references train (train_id)
		on delete cascade,
	constraint orders_users_id_fk
	foreign key (user_id) references users (id)
		on update cascade
		on delete cascade
);

# order indices
create index orders_train_id_index
	on orders (train_id);


# order_passenger table
create table if not exists order_passenger
(
	order_id     int             not null,
	passenger    varchar(10)     not null,
	passenger_id char(18)        null,
	seat_id      int             not null,
	ticket_type  int default '0' not null,
	
	# table constraints
	constraint order_passenger_orders_order_id_fk
	foreign key (order_id) references orders (order_id)
);


/**
 * Views
 */

# train_station_cross_info view
create or replace view train_station_cross_info as (select `train`.`train_station`.`train_id`      AS `train_id`,
																													 `train`.`train_station`.`station_order` AS `station_order`,
																													 `train`.`train_station`.`station`       AS `station`,
																													 `train`.`train_station`.`depart_time`   AS `depart`,
																													 `train`.`train_station`.`arrival_time`  AS `arrival`
																										from `train`.`train_station`
																										where `train`.`train_station`.`station` in
																													(select `train`.`train_station`.`station`
																													 from `train`.`train_station`
																													 group by `train`.`train_station`.`station`
																													 having (count(0) > 1)));


/**
 * Event
 */

# procedure for status update
create procedure out_of_date()
	begin
		update train.orders
		set status = -1
		where depart_date < date(now())
			and status = 0;
	end;

# event for status update
create definer = root@localhost event event_auto_cancel_order
	on schedule
		every '1' DAY
			starts '2018-09-27 13:15:00'
	on completion preserve
	enable
do
	call out_of_date();
