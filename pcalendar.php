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
    
    function __construct()
    {
        $this->_date = '';
        
        $this->createTray();
        $this->createRightMenu();
        $this->onDayChange();
        
        Gtk::timeout_add(300000 /* five minutes */, array($this, 'onDayChange'));
        
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
            $icon = file_put_contents('/tmp/today.svg', $icon);
            
            $this->_tray->set_tooltip(persian_calendar::date('j M Y'));
            $this->_tray->set_from_file('/tmp/today.svg');
            
            $this->_date = date('Y/m/d');
            
            $this->notify(persian_calendar::date('l d F Y'), $today['title']);

            @unlink('/tmp/today.svg');
        }
        return true;
    }

    private function getEvent($year, $month, $day)
    {
        foreach(glob('/usr/share/pcalendar/events/*.php') as $e){
            require($e);
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
        $mainhbox->pack_start($hbox, true,true, 0);
        
        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-previous.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'monthChangedInCalendar'), 1);
        
        $l = new GtkLabel(persian_calendar::date('F', $ts));
        $l->set_size_request(50,25);
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
        $GoToday->connect('clicked', array($this, 'goTodayInCalendar'));
        $hbox->pack_start($GoToday, false, false);
        
        // year panel
        $hbox = new GtkHBox();
        $mainhbox->pack_start($hbox, true,true, 0);
        
        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-previous.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'yearChangedInCalendar'), 1);
        
        $l = new GtkLabel(persian_calendar::date('Y', $ts));
        $hbox->pack_start($l);

        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-next.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $l->set_can_focus(false);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'yearChangedInCalendar'), -1);
        
        $this->_table = new GtkTable(1, 1, true);
        $this->_table->set_col_spacings(2);
        $this->_table->set_row_spacings(2);
        
        // main frame
        $frame = new GtkFrame();
        //$frame->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#000000'));

        $frame->add($this->_table);
        $this->_leftmenu->get_child()->pack_start($frame, true, true, 0);
        
        // fill week names
        for($d=0; $d<7; $d++){
            $b = new GtkLabel();
            $b->set_use_markup(true);
            $b->set_markup($week_names[$d]);
            $this->_table->attach($b, abs($d-6), abs($d-6)+1, 0, 1, 0, 0, 0, 0);
        }

        // print persian and gregorian dates
        $l = new GtkLabel();
        $l->set_use_markup(true);
        $l->set_padding(0,5);
        $this->_leftmenu->get_child()->pack_start($l, false, false, 0);
        $l->set_markup(persian_calendar::date('Y/m/d', $ts) . '               ' . date('Y/m/d', $ts));
        
        // fill days
        $days = array();
        $y = 1;
        
        for($d=1; $d<=$max_days; $d++){
            $weekday = persian_calendar::date('N', persian_calendar::mktime(0, 0, 0, $month, $d, $year), false);
            $days[$d] = array('x' => abs($weekday-7), 'y' => $y);
            $today = $this->getEvent($year, $month, $d);
            $days[$d] = array_merge($days[$d], $today);
            
            $labelEvent  = new GtkEventBox();
            $labelEvent->add(new GtkLabel());
            $labelEvent->get_child()->set_use_markup(true);
            $this->bs[$d] = $labelEvent->connect('button_press_event', array($this, 'dayChangedInCalendar'), $d);
            //$labelEvent->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#ffffff'));

            if($days[$d]['holiday']){
                $color = 'red';
                $selected_color = '#ff9999';
            } else {
                $color = 'black';
                $selected_color = '#ffffff';
            }
            
            if($day == $d){
                $labelEvent->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#555555')); // holiday / selected
                if($today['title']){
                    // fill event title
                    $l = new GtkLabel();
                    $l->set_use_markup(true);
                    $l->set_line_wrap(true);
                    $l->set_width_chars(32);
                    $l->set_markup("<span color=\"{$color}\">" . $today['title'] . '</span>');
                    $this->_leftmenu->get_child()->pack_start($l, false, false, 0);
                }
                $color = $selected_color;
            }
            $labelEvent->get_child()->set_markup("<big><span color=\"{$color}\">".persian_calendar::persian_no($d) . '</span></big> <span color="darkgray"><small><small>'.date('j', persian_calendar::mktime(0,0,0,$month,$d,$year)).'</small></small></span>');
            
            $frameL = new GtkFrame();
            $frameL->set_shadow_type(Gtk::SHADOW_ETCHED_OUT);
            $frameL->add($labelEvent);
            
            $this->_table->attach($frameL, $days[$d]['x'], $days[$d]['x']+1, $days[$d]['y'], $days[$d]['y']+1, Gtk::EXPAND|Gtk::FILL, Gtk::EXPAND|Gtk::FILL, 0, 0);
            
            // change Y after friday!
            if($weekday == 7) $y++;
        }
        
        $this->_leftmenu->show_all();
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
        $max_year = 1416; // limitation of 32bits operating systems!
        $min_year = 1283; // limitation of 32bits operating systems!
        
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

        $preferences = new GtkMenuItem('Preferences');
        $about = new GtkMenuItem('About');
        $quit = new GtkMenuItem('Quit');
        
        $preferences->connect('activate', array($this, 'onPreferences'));
        $about->connect('activate', array($this, 'onAbout'));
        $quit->connect('activate', array($this, 'onQuit'));
        
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

    public function onQuit()
    {
        $this->__destruct();
    }
    
    public function onAbout()
    {   
        $dlgAbout = new GtkAboutDialog();
        $dlgAbout->set_icon_from_file('/usr/share/pcalendar/pix/icon.svg');
        
        $dlgAbout->set_name('Persian Calendar');
        $dlgAbout->set_version('0.4');
         
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
    
    public function onPreferences()
    {
        $startup_file = $_SERVER['HOME'] . '/.config/autostart/pcalendar.desktop';
        
        $dlgPreferences = new GtkDialog('Persian Calendar Preferences');
        $dlgPreferences->set_icon_from_file('/usr/share/pcalendar/pix/icon.svg');
        $dlgPreferences->set_default_size(300,50);
        //$dlgPreferences->set_resizable(false);
        $dlgPreferences->set_modal(true);
        $dlgPreferences->set_skip_pager_hint(true);
        
        $notebook = new GtkNotebook();
        $dlgPreferences->vbox->pack_start($notebook);
        
        //Page General
        $vboxGeneral = new GtkVBox();
        $checkboxStartLogin = new GtkCheckButton('Start at login.');
        
        $vboxGeneral->pack_start($checkboxStartLogin);
        $this->add_new_tab($notebook, $vboxGeneral, 'General');
        
        $exists = false;
        if(file_exists($startup_file)){
            $checkboxStartLogin->set_active(true);
            $exists = true;
        }
        //End Page General
        
        //Start Window
        $dlgPreferences->add_buttons(array(
            Gtk::STOCK_CANCEL, Gtk::RESPONSE_CANCEL,
            Gtk::STOCK_OK, Gtk::RESPONSE_OK,
        )); 
        
        $dlgPreferences->show_all();
        $response_id = $dlgPreferences->run();
        $dlgPreferences->destroy();
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
        }
        //End process of Preferences
    }
    
    //Add new tab
    public function add_new_tab($notebook, $widget, $tab_label) {
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

    public function notify($title, $body)
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
