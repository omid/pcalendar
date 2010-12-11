<?php

$events_info['iran_lunar'] = array(
    'name' => 'مناسبت‌های عربی و رسمی ایران'
);

$l_events = array (

    array('day' => 58, 'title' => 'شهادت حضرت فاطمه', 'holiday' => true),
    array('day' => 75, 'title' => 'ولادت حضرت فاطمه و روز زن', 'holiday' => false),
    array('day' => 98, 'title' => 'ولادت امام علی', 'holiday' => true),
    array('day' => 112, 'title' => 'مبعث حضرت رسول اکرم', 'holiday' => true),
    array('day' => 129, 'title' => 'ولادت حضرت قائم', 'holiday' => true),
    array('day' => 165, 'title' => 'شهادت حضرت علی', 'holiday' => true),
    array('day' => 174, 'title' => 'عید فطر', 'holiday' => true),
    array('day' => 198, 'title' => 'شهادت اما جعفر صادق', 'holiday' => true),
    array('day' => 242, 'title' => 'عید قربان', 'holiday' => true),
    array('day' => 250, 'title' => 'عید غدیر خم', 'holiday' => true),
    array('day' => 270, 'title' => 'تاسوعای حسینی', 'holiday' => true),
    array('day' => 271, 'title' => 'عاشورای حسینی', 'holiday' => true),
    array('day' => 311, 'title' => 'اربعین حسینی', 'holiday' => true),
    array('day' => 319, 'title' => 'رحلت حضرت رسول اکرم و شهادت امام حسن مجتبی', 'holiday' => true),
    array('day' => 321, 'title' => 'شهادت امام رضا', 'holiday' => true),
    array('day' => 338, 'title' => 'میلاد حضرت رسول اکرم و اما جعفر صادق', 'holiday' => true),

);
/* @TODO if there is two leap year in the delta then what should I do? in the lunar calendars we have leap years or not? */
// events in lunar system shifts back 10 days each year!
if(!isset($year)){
    $year = persian_calendar::date('Y', '', false);
}

$delta = $year - 1389;
$leap = persian_calendar::date('L');

foreach($l_events as $index => $e){
    $e['day'] = $e['day'] - ($delta * 10);
    while ($e['day'] <= 0) {
        $e['day'] += 365 + $leap;
    }
    $l_events[$index] = $e;
}

$events[] = $l_events;

unset($l_events);
