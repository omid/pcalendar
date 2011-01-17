#!/usr/bin/php
<?php

ini_set('php-gtk.codepage', 'UTF-8');

require_once('/usr/share/pcalendar/persian_calendar.php');

class Calendar
{
    protected $_rightmenu;
    protected $_leftmenu;
    protected $_leftmenu_visible = false;
    protected $_tray;
    protected $_date;
    protected $_label;
    protected $events;
    protected $icsCals;
    
    function __construct()
    {
        $this->_date = '';
        
        $this->loadInitialEvents();
        $this->loadConfig();
        
        $this->createTray();
        $this->createRightMenu();
        $this->onDayChange();
        
        Gtk::timeout_add(300000 /* five minutes */, array($this, 'onDayChange'));
        
        Gtk::timeout_add(300000 /* five minutes */, array($this, 'onSync'));
        
        Gtk::main();
    }

    public function onDayChange()
    {
        if($this->_date != date('Y/m/d')){
            $today = $this->getEvent(persian_calendar::date('Y', '', false), persian_calendar::date('m', '', false), persian_calendar::date('d', '', false));
            
            if($today['holiday']){
                $icon = '/usr/share/pcalendar/pix/holiday.svg';
            } else {
                $icon = '/usr/share/pcalendar/pix/normalday.svg';
            }
            $icon = file_get_contents($icon);
            $icon = str_replace('۱۰', persian_calendar::date('d'), $icon);
            file_put_contents('/tmp/today.svg', $icon);
            
            $this->_tray->set_tooltip(persian_calendar::date('j M Y'));
            $this->_tray->set_from_file('/tmp/today.svg');
            
            $this->_date = date('Y/m/d');
            
            $this->notify(persian_calendar::date('l d F Y'), $today['title']);

            @unlink('/tmp/today.svg');
        }
        return true;
    }

    private function loadInitialEvents()
    {
        foreach(glob('/usr/share/pcalendar/events/*.php') as $e){
            $key = substr(basename($e), 0, strrpos(basename($e), '.'));
            // if event list already defined and is active include that file!
            if(!isset($this->events[$key]) || @$this->events[$key]['active']) require($e);
        }

        foreach($events_info as $key => $val){
            if(!isset($this->events[$key])){
                $this->events[$key] = $val;
            }
        }
    }
    
    private function getEvent($year, $month, $day)
    {
        $events = $events_info = array();
        
        foreach(glob('/usr/share/pcalendar/events/*.php') as $e){
            $key = substr(basename($e), 0, strrpos(basename($e), '.'));
            // if event list already defined and is active include that file!
            if(!isset($this->events[$key]) || @$this->events[$key]['active']) require($e);
        }

        foreach($events_info as $key => $val){
            if(!isset($this->events[$key])){
                $this->events[$key] = $val;
            }
        }
        
        $ts = persian_calendar::mktime(0, 0, 0, $month, $day, $year);
        
        // find today has an event or not? / is it holiday or not?
        $today['day'] = persian_calendar::date('z', $ts, false);
        $today['title'] = '';
        $today['holiday'] = false;
        
        foreach($events as $event){
            foreach($event as $e){
                if($e['day'] == $today['day']){
                    $today['holiday'] = $today['holiday'] || $e['holiday'];
                    if($today['title']) $today['title'] .= "\n";
                    $today['title'] .= $e['title'];
                }
            }
        }
        
        // if it is friday
        if(persian_calendar::date('N', $ts, false) == 7) $today['holiday'] = true;

        return $today;
    }

    private function renderCalendar($year, $month, $day)
    {
        $max_days = persian_calendar::date('t', persian_calendar::mktime(0, 0, 0, $month, 1, $year), false);

        // check if this month have desired day
        if($max_days < $this->day) $this->day = $day = $max_days;
        
        $ts = persian_calendar::mktime(1, 0, 0, $month, $day, $year);

        $this->_leftmenu->resize(1,1);
        if($this->_leftmenu->get_child()){
            $this->_leftmenu->get_child()->destroy();
        }
        $this->_leftmenu->add(new GtkVBox());
        //$this->_leftmenu->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#FFFFFF'));
        $week_names = array('شنبه', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه');
        
        // month panel
        $mainhbox = new GtkHBox();
        $this->_leftmenu->get_child()->pack_start($mainhbox, true, true, 0);

        $hbox = new GtkHBox();
        $mainhbox->pack_start($hbox, false, false, 0);
        
        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-previous.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'monthChangedInCalendar'), 1);
        
        $l = new GtkLabel(persian_calendar::date('F', $ts));
        $l->set_size_request(55,25);
        $l->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
        $hbox->pack_start($l);

        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-next.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'monthChangedInCalendar'), -1);
        
        // go today button
        $GoToday = new GtkButton('امروز');
        $GoToday->set_relief(Gtk::RELIEF_NONE);
        $GoToday->set_can_focus(false);
        $GoToday->get_child()->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
        $GoToday->connect('clicked', array($this, 'goTodayInCalendar'));
        $mainhbox->pack_start($GoToday, true, true);
        
        // year panel
        $hbox = new GtkHBox();
        $mainhbox->pack_start($hbox, false, false, 0);
        
        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-previous.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'yearChangedInCalendar'), 1);
        
        $l = new GtkLabel(persian_calendar::date('Y', $ts));
        $l->set_size_request(35,25);
        $l->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
        $hbox->pack_start($l);

        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-next.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'yearChangedInCalendar'), -1);
        
        $this->_table = new GtkTable();
        //$this->_table->set_col_spacings(2);
        //$this->_table->set_row_spacings(2);
        
        // main frame
        $frame = new GtkFrame();

        $frame->add($this->_table);
        $this->_leftmenu->get_child()->pack_start($frame, true, true, 0);
        
        // fill week names
        for($d=0; $d<7; $d++){
            $b = new GtkLabel();
            $b->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
            $b->set_use_markup(true);
            $b->set_markup($week_names[$d]);
            $this->_table->attach($b, abs($d-6), abs($d-6)+1, 0, 1, 0, 0, 0, 0);
        }

        // print persian and gregorian dates
        $l = new GtkLabel();
        $l->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
        $l->set_use_markup(true);
        $l->set_padding(0,5);
        $frameL = new GtkFrame();
        $frameL->add($l);
        $this->_leftmenu->get_child()->pack_start($frameL, false, false, 0);
        $l->set_markup(persian_calendar::date('Y/m/d', $ts) . '               ' . date('Y/m/d', $ts));
        
        // fill days
        $days = array();
        $y = 1;
        
        $first_day_of_month = null;
        $last_day_of_month  = null;
        
        for($d=1; $d<=$max_days; $d++){
            $weekday = persian_calendar::date('N', persian_calendar::mktime(0, 0, 0, $month, $d, $year), false);
            
            if($first_day_of_month === null) $first_day_of_month = abs($weekday-7);
            if($d == $max_days) $last_day_of_month = abs($weekday-7);

            $days[$d] = array('x' => abs($weekday-7), 'y' => $y);
            $today = $this->getEvent($year, $month, $d);
            $days[$d] = array_merge($days[$d], $today);
            
            $labelEvent  = new GtkEventBox();
            $labelEvent->add(new GtkLabel());
            $labelEvent->get_child()->modify_font(new PangoFontDescription('FreeFarsi Regular 11'));
            $labelEvent->get_child()->set_use_markup(true);
            $this->bs[$d] = $labelEvent->connect('button_press_event', array($this, 'dayChangedInCalendar'), $d);

            if($days[$d]['holiday']){
                $color = 'red';
            } else {
                $color = 'black';
            }
            
            if($day == $d){
                $labelEvent->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#dfdfdf')); // holiday / selected
                if($today['title']){
                    // fill event title
                    $l = new GtkLabel();
                    $l->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
                    $l->set_use_markup(true);
                    $l->set_line_wrap(true);
                    $l->set_width_chars(32);
                    $l->set_alignment(1,0);
                    $l->set_markup("<span color=\"{$color}\">" . $today['title'] . '</span>');
                    $this->_leftmenu->get_child()->pack_start($l, false, false, 0);
                }

                //other calenders from ics
                if($this->icsCals != '' && isset($this->calEvents))
                {
                    foreach($this->calEvents as $calEvent)
                    {
                        if(preg_match('/DTSTART.*?:(\d{8})?/', $calEvent, $eventStart))
                        {
                            if ($eventStart[1] == date('Ymd', $ts))
                            {
                                $startTime = trim($this->SelectPregMatch('/DTSTART.*?T(\d{4})?/', $calEvent));
                                $summary = trim($this->SelectPregMatch('/SUMMARY:(.*)/', $calEvent));
                                $description = trim($this->SelectPregMatch('/DESCRIPTION:(.*)/', $calEvent));
                                $location = trim($this->SelectPregMatch('/LOCATION:(.*)/', $calEvent));

                                //$strCals = "<span color=\"#19195E\">{$startTime} > {$summary}";
                                $strCals = "<span color=\"#19195E\">{$summary}";
                                if($location != '')
                                {
                                    $strCals .= " ({$location})";
                                }
                                if($description != '')
                                {
                                    $strCals .= ": $description";
                                }
                                $strCals .="</span>";
                                
                                $l = new GtkLabel();
                                $l->modify_font(new PangoFontDescription('FreeFarsi Regular 10'));
                                $l->set_use_markup(true);
                                $l->set_line_wrap(true);
                                $l->set_alignment(1,0);
                                $l->set_width_chars(32);
                                $l->set_markup($strCals);
                                $this->_leftmenu->get_child()->pack_start($l, false, false, 0);
                            }
                        }
                    }
                }
                
            }
            $labelEvent->get_child()->set_markup(" <big><span color=\"{$color}\">".persian_calendar::persian_no($d) . '</span></big> <span color="darkgray"><small><small>'.date('j', persian_calendar::mktime(0,0,0,$month,$d,$year)).'</small></small></span> ');
            
            $this->_table->attach($labelEvent, $days[$d]['x'], $days[$d]['x']+1, $days[$d]['y'], $days[$d]['y']+1, Gtk::FILL, Gtk::FILL, 0, 0);
            
            // change Y after friday!
            if($weekday == 7) $y++;
        }
        
        // fill empty cells of the table
        for($first_day_of_month = $first_day_of_month+1; $first_day_of_month<=6; $first_day_of_month++){
            $l  = new GtkEventBox();
            $l->add(new GtkLabel());
            $this->_table->attach($l, $first_day_of_month, $first_day_of_month+1, 1, 2, Gtk::FILL, Gtk::FILL, 0, 0);
        }

        for($last_day_of_month = $last_day_of_month-1; $last_day_of_month>=0; $last_day_of_month--){
            $l  = new GtkEventBox();
            $l->add(new GtkLabel());
            $this->_table->attach($l, $last_day_of_month, $last_day_of_month+1, $y, $y+1, Gtk::FILL, Gtk::FILL, 0, 0);
        }
        
        $this->_leftmenu->show_all();
    }

    private function SelectPregMatch($pattern, $input)
    {
        preg_match($pattern, $input, $matches);
        if(isset($matches[1]))
        {
            return $matches[1];
        }else
        {
            //return null;
            return '';
        }
    }
    
    public function goTodayInCalendar()
    {
        $this->year = persian_calendar::date('Y', '', false);
        $this->month = persian_calendar::date('n', '', false);
        $this->day = persian_calendar::date('j', '', false);
        $this->dateChangedInCalendar();
    }
    
    public function monthChangedInCalendar($obj, $val)
    {
        $month = $this->month;
        $year  = $this->year;

        $type = '';
        
        $this->month += $val;
        if($this->month<1){
            $this->year--;
            if($this->checkYear()){
                $this->month=12;
            }
        }
        if($this->month>12){
            $this->year++;
            if($this->checkYear()){
                $this->month=1;
            }
        }
        
        if($this->month != $month){
            $this->dateChangedInCalendar();
        }
    }

    public function checkYear()
    {
        $max_year = 1417; // limitation of 32bits operating systems!
        $min_year = 1282; // limitation of 32bits operating systems!
        
        if($this->year<$min_year){
            $this->year=$min_year;
            return false;
        }
        if($this->year>$max_year){
            $this->year=$max_year;
            return false;
        }
        return true;
    }
    public function yearChangedInCalendar($obj, $val)
    {
        $year = $this->year;
        $this->year += $val;

        $this->checkYear();
        
        if($this->year != $year){
            $this->dateChangedInCalendar();
        }
    }
    
    public function dayChangedInCalendar($widget, $event, $day)
    {
        //$widget->disconnect($this->bs[$day]);
        
        if($this->day != $day){
            $this->day = $day;
            $this->dateChangedInCalendar();
        }
    }
    
    public function dateChangedInCalendar()
    {
        $this->renderCalendar($this->year, $this->month, $this->day);
    }
    
    private function createTray()
    {
        $this->_tray = new GtkStatusIcon();
        $this->_tray->connect('popup-menu', array($this, 'onRightMenu'));
        $this->_tray->connect('activate', array($this, 'onLeftMenu'));
        $this->_tray->set_visible(true);
        $this->_tray->set_blinking(false);
    }

    private function createRightMenu()
    {
        $this->_rightmenu = new GtkMenu();
        $this->_rightmenu->set_direction(2);
        
        $showNotify = new GtkMenuItem('نمایش تاریخ');
        $showNorouzTime = new GtkMenuItem('لحظه تحویل سال نو');
        $sync = new GtkMenuItem('هماهنگ سازی با تقویم‌های دیگر');
        $preferences = new GtkMenuItem('تنظیمات');
        $about = new GtkMenuItem('درباره');
        $quit = new GtkMenuItem('خروج');
        
        $showNotify->connect('activate', array($this, 'onShowNotify'));
        $showNorouzTime->connect('activate', array($this, 'onShowNorouzTime'));
        $sync->connect('activate', array($this, 'onSync'), true);
        $preferences->connect('activate', array($this, 'onPreferences'));
        $about->connect('activate', array($this, 'onAbout'));
        $quit->connect('activate', array($this, 'onQuit'));
        
        $this->_rightmenu->append($showNotify);
        $this->_rightmenu->append($showNorouzTime);
        $this->_rightmenu->append(new GtkSeparatorMenuItem());
        $this->_rightmenu->append($sync);
        $this->_rightmenu->append($preferences);
        $this->_rightmenu->append($about);
        $this->_rightmenu->append(new GtkSeparatorMenuItem());
        $this->_rightmenu->append($quit);
        
        $this->_rightmenu->show_all();
        GtkStatusIcon::position_menu($this->_rightmenu, $this->_tray);
    }

    private function createLeftMenu()
    {
        $this->_leftmenu = new GtkWindow();
        $this->_leftmenu->set_position(Gtk::WIN_POS_MOUSE);
        $this->_leftmenu->set_decorated(false);
        $this->_leftmenu->set_skip_taskbar_hint(true);
        $this->_leftmenu->set_skip_pager_hint(true);
        $this->_leftmenu->set_border_width(5);
        $this->_leftmenu->set_keep_above(true);
        $this->_leftmenu->stick();
        
        $this->year = persian_calendar::date('Y', '', false);
        $this->month = persian_calendar::date('n', '', false);
        $this->day = persian_calendar::date('j', '', false);
        $this->dateChangedInCalendar();
    }
    
    function __destruct()
    {
        Gtk::main_quit();
        exit();
    }

    public function onShowNorouzTime()
    {
        // 31556912 is length of each Persian year, according to ghiasabadi.com
        $start = persian_calendar::mktime(21, 2, 13, 12, 29, 1388);
        $year = persian_calendar::date('Y', '', false) + 1;
        
        $delta = persian_calendar::date('Y', '', false) - 1389 + 1;

        $start = $start + ($delta * 31556912);
        if ($start <= 0) {
            $start = 0;
        }
        
        $mTo = 12 - persian_calendar::date('m', '', false);
        if($mTo >= 6)
        {
            $dTo = 31 - persian_calendar::date('d', '', false);
    }else
    {
        $dTo = 30 - persian_calendar::date('d', '', false);
    }
        if($mTo != 0)
        {
        $toNorouz = persian_calendar::persian_no($mTo) . ' ماه و ';
    }
    $toNorouz .= persian_calendar::persian_no($dTo) . ' روز مانده به ';
    
        $msg = $toNorouz . 'تحویل سال ' . persian_calendar::persian_no($year);
        $this->notify($msg, persian_calendar::date('l d F Y ساعت H و i دقیقه و s ثانیه', $start));
    }
    
    public function onQuit()
    {
        $this->__destruct();
    }
    
    public function onAbout()
    {   
        $dlgAbout = new GtkAboutDialog();
        $dlgAbout->set_icon_from_file('/usr/share/pcalendar/pix/icon.svg');
        
        $dlgAbout->set_name('Persian Calendar');
        $dlgAbout->set_version('0.7');
         
        $dlgAbout->set_comments('Persian Calendar is a calendar for Persians.');
        $dlgAbout->set_copyright('GPL version 3');
        $logo = GdkPixbuf::new_from_file('/usr/share/pcalendar/pix/icon.svg');
        $logo = $logo->scale_simple(32, 32, Gdk::INTERP_HYPER);
        $dlgAbout->set_logo($logo);
        $dlgAbout->set_website('https://github.com/omid/pcalendar');
        $dlgAbout->set_authors(array("Omid Mottaghi <omidmr@gmail.com>\nMostafa Mirmousavi <mirmousavi.m@gmail.com>\nAnd maybe you, call us through the website!"));

        $dlgAbout->set_skip_pager_hint(true);
        
        $dlgAbout->run();
        $dlgAbout->destroy();
    }
    
    public function onShowNotify()
    {
        $today = $this->getEvent(persian_calendar::date('Y', '', false), persian_calendar::date('m', '', false), persian_calendar::date('d', '', false));
        
        if($today['holiday']){
            $icon = '/usr/share/pcalendar/pix/holiday.svg';
        } else {
            $icon = '/usr/share/pcalendar/pix/normalday.svg';
        }
        $icon = file_get_contents($icon);
        $icon = str_replace('۱۰', persian_calendar::date('d'), $icon);
        file_put_contents('/tmp/today.svg', $icon);

        $this->notify(persian_calendar::date('l d F Y'), $today['title']);

        @unlink('/tmp/today.svg');
    }
    
    public function onSync($msg = false)
    {
        if(is_array($this->icsCals) && isset($this->calEvents))
        {            
            $syncRead = false;
            $fpTmp = '';
            foreach($this->icsCals as $icsCal)
            {
                if($fpTmp .= @file_get_contents($icsCal))
                {
                  $syncRead = true;
                }
            }            
            
            if($syncRead)
            {
                $icsCalCacheFile = $_SERVER['HOME'] . '/.config/pcalendar/cache.ics';
                @unlink($icsCalCacheFile);
                @mkdir($_SERVER['HOME'] . '/.config/pcalendar/');
                touch($icsCalCacheFile);
                $fp = fopen($icsCalCacheFile, 'a');
                fwrite($fp, $fpTmp);
                fclose($fp); 
            }
            
            $this->loadSyncCache();
            
            if($msg)
            {
                if($syncRead)
                {
                    $this->notify('هماهنگ سازی با تقویم‌های دیگر', 'هماهنگ سازی با موفقیت انجام شد');            
                }else
                {
                    $this->notify('هماهنگ سازی با تقویم‌های دیگر', 'متاسفانه هماهنگ سازی انجام نشد. در اتصال به تقویم‌های دیگر مشکلی وجود دارد.');            
                }
            }
        }
        else{
            $this->notify('هماهنگ سازی با تقویم‌های دیگر', 'هیچ تقویمی وارد نشده است. لطفن برای وارد نمودن تقویم از قسمت تنظیمات استفاده نمایید.');            
        }
        return true;
    }
    
    private function loadSyncCache()
    {
        $icsCalCacheFile = $_SERVER['HOME'] . '/.config/pcalendar/cache.ics';
        $icsCalBuffer = @file_get_contents($icsCalCacheFile);
        
        if(isset($icsCalBuffer))
        {
            unset($this->calEvents);
            
            $icsCalBuffer = preg_split("/(BEGIN:VEVENT)/", $icsCalBuffer);
            $this->calEvents = $icsCalBuffer;
        }
    }
    
    public function onPreferences()
    {
        $startup_file = $_SERVER['HOME'] . '/.config/autostart/pcalendar.desktop';
        
        $dlgPreferences = new GtkDialog('تنظیمات Persian Calendar');
        $dlgPreferences->set_icon_from_file('/usr/share/pcalendar/pix/icon.svg');
        $dlgPreferences->set_default_size(300,200);
        //$dlgPreferences->set_resizable(false);
        $dlgPreferences->set_modal(true);
        $dlgPreferences->set_skip_pager_hint(true);
        
        $notebook = new GtkNotebook();
        $notebook->set_direction(2);
        $dlgPreferences->vbox->pack_start($notebook);
        
        //Page General
        $vboxGeneral = new GtkVBox();
        $checkboxStartLogin = new GtkCheckButton('راه‌اندازی برنامه در زمان ورود');
        $checkboxStartLogin->set_direction(2);
        
        $vboxGeneral->pack_start($checkboxStartLogin, false, false, 5);
        $this->add_new_tab($notebook, $vboxGeneral, 'عمومی');
        
        $exists = false;
        if(file_exists($startup_file)){
            $checkboxStartLogin->set_active(true);
            $exists = true;
        }
        //End Page General
        
        // start events page
        $vboxEvents = new GtkVBox();
        
        foreach($this->events as $key => $val)
        {
            $this->events[$key]['handle'] = new GtkCheckButton($val['name']);
            $this->events[$key]['handle']->set_direction(2);
            $vboxEvents->pack_start($this->events[$key]['handle'], false, false, 5);
        }
        
        $this->add_new_tab($notebook, $vboxEvents, 'مناسبت‌ها');

        // set config to checkboxes
        foreach($this->events as $key => $val)
        {
            if($this->events[$key]['active'])
            {
                $this->events[$key]['handle']->set_active(true);
            }
        }
        //End events page
        
        // start sync page
        $vboxSync = new GtkVBox();
        
        $icsCalsBuffer = new GtkTextBuffer();
        if(is_array($this->icsCals))
        {
            $icsCalsBuffer->set_text(implode("\n", $this->icsCals));            
        }
        
        $view = new GtkTextView();
        $view->set_buffer($icsCalsBuffer);
        
        $scrolled_win = new GtkScrolledWindow();
        $scrolled_win->set_policy( Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
        $scrolled_win->add($view);
        
        $btnGoogleHelp = new GtkButton('نمایش عکس نمونه لینک خروجی گوگل کلندر');
        $btnGoogleHelp->connect('clicked', array($this, 'onGoogleCalendarHelp'));

        $vboxSync->pack_start(new GtkLabel('دریافت و نمایش از تقویم‌های دیگر:'), 0, 0);
        $vboxSync->pack_start($scrolled_win, 0, 0);
        $vboxSync->pack_start(new GtkLabel('شما می‌توانید برای نمایش رویدادهای تقویم‌های ICAL خود، آدرس آن‌ها را در کادر بالا وارد کنید.'), 0, 0);
        $vboxSync->pack_start(new GtkLabel('لطفن برای وارد کردن چند تقویم، آدرس هر یک را در خطی جداگانه بنویسید.'), 0, 0);
        $vboxSync->pack_start($btnGoogleHelp, 0, 0);
        $this->add_new_tab($notebook, $vboxSync, 'ارتباطات');
        //End sync page
        
        //Start Window
        $dlgPreferences->add_buttons(array(
            Gtk::STOCK_CANCEL, Gtk::RESPONSE_CANCEL,
            Gtk::STOCK_OK, Gtk::RESPONSE_OK,
        )); 
        
        $dlgPreferences->show_all();
        $response_id = $dlgPreferences->run();
        //Stop Window
        
        //process of Preferences
        if($response_id == Gtk::RESPONSE_OK)
        {
            //process of General tab
            if($checkboxStartLogin->get_active())
            {
                if(!$exists)
                {
                    copy('/usr/share/pcalendar/pcalendar.desktop', $startup_file);
                }
            }else
            {
                @unlink($startup_file);
            }
            //End process of General tab
            
            //process of Events tab
            foreach($this->events as $key => $val)
            {
                if($this->events[$key]['handle']->get_active())
                {
                    $this->events[$key]['active'] = true;
                } else {
                    $this->events[$key]['active'] = false;
                }
            }
            $this->saveConfig();
            //End process of Events tab
            
            //process of sync tab
            $syncConfigFile = $_SERVER['HOME'] . '/.config/pcalendar/sync.conf';
            
            $icsCalsText = $icsCalsBuffer->get_text(
                                             $icsCalsBuffer->get_start_iter(),
                                             $icsCalsBuffer->get_end_iter());
            
            
            if($icsCalsText != '')
            {
                foreach(explode("\n", $icsCalsText) as $line)
                {
                    $cal[] = trim($line);
                }
                if(is_array($cal))
                {
                    $this->icsCals = $cal;                    
                }
                file_put_contents($syncConfigFile, json_encode($this->icsCals));
            }else
            {
                $this->icsCals = array();
                file_put_contents($syncConfigFile, json_encode(''));
            }
            //End process of sync tab

        }
        //End process of Preferences
        
        $dlgPreferences->destroy();
    }
    
    public function onGoogleCalendarHelp()
    {
        $dlgGoogleCalendarHelp = new GtkDialog('تصویر راهنما گوگل کلندر');
        $dlgGoogleCalendarHelp->set_icon_from_file('/usr/share/pcalendar/pix/icon.svg');
        $dlgGoogleCalendarHelp->set_resizable(false);
        $dlgGoogleCalendarHelp->set_modal(true);
        $dlgGoogleCalendarHelp->set_skip_pager_hint(true);

        $img = GtkImage::new_from_file('/usr/share/pcalendar/pix/googlecalendar.jpg');

        $dlgGoogleCalendarHelp->vbox->pack_start($img);
        
        $dlgGoogleCalendarHelp->show_all();
        //$dlgPreferences->destroy();
    }
    
    private function loadConfig()
    {
        $eventsConfigFile = $_SERVER['HOME'] . '/.config/pcalendar/events.conf';
        $syncConfigFile = $_SERVER['HOME'] . '/.config/pcalendar/sync.conf';

        if(!file_exists($eventsConfigFile) || !file_exists($syncConfigFile)){
            $this->saveConfig();
        }
        
        $eventsConfigBuffer = trim(file_get_contents($eventsConfigFile));
        
        if($eventsConfigBuffer) // default config!
        {
            foreach($this->events as $key => $val)
            {
                $this->events[$key]['active'] = true;
            }
        }else
        {
            $eventsConfigBuffer = json_decode($eventsConfigBuffer);
            foreach($eventsConfigBuffer['events'] as $e)
            {
                $this->events[$key]['active'] = true;
            }
            foreach($this->events as $key => $val)
            {
                if(in_array($key, $eventsConfigBuffer)){
                    $this->events[$key]['active'] = true;
                } else {
                    $this->events[$key]['active'] = false;
                }
            }
        }
        
        $syncConfigFile = $_SERVER['HOME'] . '/.config/pcalendar/sync.conf';        
        if(file_exists($syncConfigFile)){
            $this->icsCals = json_decode(file_get_contents($syncConfigFile));
            
            if(is_array($this->icsCals))
            {
                $this->loadSyncCache();
            }
        }
        
    }

    private function saveConfig()
    {
        //events config
        $eventsConfigFile = $_SERVER['HOME'] . '/.config/pcalendar/events.conf';
        
        if(!file_exists($eventsConfigFile))
        {
            @mkdir($_SERVER['HOME'] . '/.config/pcalendar/');
            touch($eventsConfigFile);
        }

        $eventsTMP['events'] = array();
        foreach($this->events as $key => $val)
        {
            if(!isset($this->events[$key]['active']) || $this->events[$key]['active'])
            {
                $eventsTMP[] = $key;
            }
        }
        file_put_contents($eventsConfigFile, json_encode($eventsTMP));
        
        //sync config
        $syncConfigFile = $_SERVER['HOME'] . '/.config/pcalendar/sync.conf';
        
        if(!file_exists($syncConfigFile))
        {
            @mkdir($_SERVER['HOME'] . '/.config/pcalendar/');
            touch($syncConfigFile);
        }
    }
    
    public function add_new_tab($notebook, $widget, $tab_label)
    {
        $eventbox = new GtkEventBox();
        $label = new GtkLabel($tab_label);
        $eventbox->add($label);
        $label->show();
        $notebook->append_page($widget, $eventbox);
    }
    
    public function onRightMenu()
    {
        $this->_rightmenu->popup();
    }

    public function onLeftMenu()
    {
        if($this->_leftmenu_visible){
            $this->_leftmenu->hide_all();
            if(isset($this->_leftmenu)) $this->_leftmenu->destroy();
            $this->_leftmenu_visible = false;
        } else {
            $this->createLeftMenu();
            $this->_leftmenu->show_all();
            $this->_leftmenu_visible = true;
        }
    }

    public function notify($title, $body='')
    {
        $d = new Dbus( Dbus::BUS_SESSION );
        $n = $d->createProxy('org.freedesktop.Notifications', '/org/freedesktop/Notifications', 'org.freedesktop.Notifications');
        
        $id = $n->Notify(
            'Persian Calendar', new DBusUInt32( 0 ), // app_name, replaces_id
            '/tmp/today.svg', $title, $body, // app_icon, summary, body
            new DBusArray( DBus::STRING, array() ), // actions
            new DBusDict(                           // hints
                DBus::VARIANT,
                array(
                    'x' => new DBusVariant( 500 ),  // x position on screen
                    'y' => new DBusVariant( 500 ),  // y position on screen
                    'desktop-entry' => new DBusVariant( 'pcalendar' )  // the name of the desktop filename representing the calling program
                )
            ),
            5000 // expire timeout in msec
        );
    }
}

$app = new Calendar();
