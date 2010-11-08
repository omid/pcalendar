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
        foreach($l_events as $e){
            if($e['day'] == $today['day']){
                $today = $e;
            }
        }
        
        foreach($s_events as $e){
            if($e['day'] == $today['day']){
                $today['holiday'] = $today['holiday'] || $e['holiday'];
                if($today['title']) $today['title'] .= "\n";
                $today['title'] .= $e['title'];
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
        
        $ts = persian_calendar::mktime(0, 0, 0, $month, $day, $year);

        $this->_leftmenu->resize(1,1);
        if($this->_leftmenu->get_child()){
            $this->_leftmenu->get_child()->destroy();
        }
        $this->_leftmenu->add(new GtkVBox());
        
        $this->_table = new GtkTable(1, 1, true);
        $this->_table->set_col_spacings(2);
        $this->_table->set_row_spacings(2);
        $this->_leftmenu->get_child()->pack_start($this->_table, true, true, 0);
        
        $week_names = array('شنبه', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه');
        
        // month panel
        $hbox = new GtkHBox();
        $this->_table->attach($hbox, 0, 4, 0, 1, Gtk::FILL, Gtk::FILL, 0, 0);
        
        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-previous.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'monthChangedInCalendar'), 1);
        
        $l = new GtkLabel(persian_calendar::date('F', $ts));
        $hbox->pack_start($l);

        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-next.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'monthChangedInCalendar'), -1);
        
        // go today button
        $GoToday = new GtkButton('امروز');
        $GoToday->set_relief(Gtk::RELIEF_NONE);
        $GoToday->connect('clicked', array($this, 'goTodayInCalendar'));
        $hbox->pack_start($GoToday, false, false);
        
        // year panel
        $hbox = new GtkHBox();
        $this->_table->attach($hbox, 4, 7, 0, 1, Gtk::FILL, Gtk::FILL, 0, 0);
        
        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-previous.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'yearChangedInCalendar'), 1);
        
        $l = new GtkLabel(persian_calendar::date('Y', $ts));
        $hbox->pack_start($l);

        $l = new GtkButton();
        $l->set_image(GtkImage::new_from_file('/usr/share/pcalendar/pix/go-next.svg'));
        $l->set_relief(Gtk::RELIEF_NONE);
        $hbox->pack_start($l, false, false);
        $l->connect('clicked', array($this, 'yearChangedInCalendar'), -1);
        
        // fill week names
        for($d=0; $d<7; $d++){
            $b = new GtkLabel('');
            $b->set_use_markup(true);
            $b->set_markup($week_names[$d]);
            $this->_table->attach($b, abs($d-6), abs($d-6)+1, 1, 2, 0, 0, 0, 0);
        }

        // print persian and gregorian dates
        $l = new GtkLabel();
        $l->set_use_markup(true);
        $l->set_justify(Gtk::JUSTIFY_CENTER);
        $l->set_padding(0,5);
        $this->_leftmenu->get_child()->pack_start($l, false, false, 0);
        $str = persian_calendar::date('Y/m/d', $ts) . '               ' . date('Y/m/d', $ts);
        $l->set_markup($str);
        
        // fill days
        $days = array();
        $y = 2;
        
        for($d=1; $d<=$max_days; $d++){
            $weekday = persian_calendar::date('N', persian_calendar::mktime(0, 0, 0, $month, $d, $year), false);
            $days[$d] = array('x' => abs($weekday-7), 'y' => $y);
            $today = $this->getEvent($year, $month, $d);
            $days[$d] = array_merge($days[$d], $today);
            
            $b = new GtkToggleButton('');
            $b->set_can_focus(false);
            if($days[$d]['holiday']){
                $b->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#FFEEEE')); // holiday
                $b->modify_bg(Gtk::STATE_ACTIVE, GdkColor::parse('#FFCCCC')); // holiday / selected
                $b->modify_bg(Gtk::STATE_PRELIGHT, GdkColor::parse('#FFFEFE')); // holiday / hover
            } else {
                $b->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#EEEEFF')); // normal day
                $b->modify_bg(Gtk::STATE_ACTIVE, GdkColor::parse('#CCCCFF')); // normal day / selected
                $b->modify_bg(Gtk::STATE_PRELIGHT, GdkColor::parse('#FEFEFF')); // normal day / hover
            }
            
            $this->bs[$d] = $b->connect_simple('toggled', array($this, 'dayChangedInCalendar'), $b, $d);
            $b->get_child()->set_use_markup(true);
            $b->get_child()->set_markup(persian_calendar::persian_no($d) . ' <span color="darkgray"><small><small>'.date('j', persian_calendar::mktime(0,0,0,$month,$d,$year)).'</small></small></span>');
            if($day == $d){
                $b->set_active(true);
                if($today['title']){
                    // fill event title
                    $l = new GtkLabel('', true);
                    $l->set_use_markup(true);
                    $l->set_justify(Gtk::JUSTIFY_CENTER);
                    $this->_leftmenu->get_child()->pack_start($l, false, false, 0);
                    $l->modify_fg(Gtk::STATE_ACTIVE, GdkColor::parse('#FFCCCC')); // holiday
                    $l->set_markup('<b>' . $today['title'] . '</b>');
                }
            }
            $this->_table->attach($b, $days[$d]['x'], $days[$d]['x']+1, $days[$d]['y'], $days[$d]['y']+1, Gtk::FILL, Gtk::FILL, 0, 0);
            
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
        $this->month += $val;
        if($this->month<1){
            $this->month=12;
            $this->year--;
        }
        if($this->month>12){
            $this->month=1;
            $this->year++;
        }
        if($this->month != $month){
            $this->dateChangedInCalendar();
        }
    }

    public function yearChangedInCalendar($obj, $val)
    {
        $max_year = 10000;
        $year = $this->year;
        $this->year += $val;
        if($this->year<1) $this->year=1;
        if($this->year>$max_year) $this->year=$max_year;
        if($this->year != $year){
            $this->dateChangedInCalendar();
        }
    }
    
    public function dayChangedInCalendar($b, $day)
    {
        $b->disconnect($this->bs[$day]);
        
        $days = $this->_table->get_children();
        foreach($days as $d){
            if(get_class($d) == 'GtkToggleButton'){
                $d->set_active(false);
            }
        }
        $b->set_active(true);
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
         
        $dlgAbout->set_name('Persian Calendar');
        $dlgAbout->set_version('0.3');
         
        $dlgAbout->set_comments('Persian Calendar is a calendar for Persians.');
        $dlgAbout->set_copyright('GPL version 3');
        $logo = GdkPixbuf::new_from_file('/usr/share/pcalendar/pix/icon.svg');
        $logo = $logo->scale_simple(32, 32, Gdk::INTERP_HYPER);
        $dlgAbout->set_logo($logo);
        $dlgAbout->set_website('https://github.com/omid/pcalendar'); // link
        $dlgAbout->set_authors(array("Omid Mottaghi\nMostafa Mirmousavi\nAnd maybe you, call us through the website!"));
        $dlgAbout->set_skip_taskbar_hint(true);
        $dlgAbout->set_skip_pager_hint(true);
        
        $dlgAbout->run();
        $dlgAbout->destroy();
    }
    
    public function onPreferences()
    {
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
        $n = $d->createProxy("org.freedesktop.Notifications", "/org/freedesktop/Notifications", "org.freedesktop.Notifications");
        
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
