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
    
    public function __construct()
    {
        $this->_date = '';
        
        $this->createTray();
        $this->createRightMenu();
        $this->onDayChange();
        
        Gtk::timeout_add(600000 /* ten minutes */, array($this, 'onDayChange'));
        
        Gtk::main();
    }

    public function onDayChange()
    {
        if($this->_date != date('Y/m/d')){
            $today = $this->getEvent(persian_calendar::date('Y', '', false), persian_calendar::date('m', '', false), persian_calendar::date('d', '', false));
            
            if($today['holiday']){
                $im = imagecreatefrompng('/usr/share/pcalendar/icon-holiday.png');
            } else {
                $im = imagecreatefrompng('/usr/share/pcalendar/icon.png');
            }
            $fg = imagecolorallocate($im, 230, 230, 230);
            $font = '/usr/share/fonts/truetype/ttf-dejavu/DejaVuSans-Bold.ttf';
            imagettftext($im, 11, 0, 5, 21, $fg, $font, persian_calendar::date('d'));
            $pixbuf = GdkPixbuf::new_from_gd($im);
            imagedestroy($im);

            $this->_tray->set_tooltip(persian_calendar::date('j M Y'));
            
            $this->_tray->set_from_pixbuf($pixbuf);
            
            $this->_date = date('Y/m/d');
            
            $this->notify(persian_calendar::date('l d F Y'), $today['title']);
            
        }
        return true;
    }

    private function getEvent($year, $month, $day)
    {
        require('/usr/share/pcalendar/events/solar.php');
        require('/usr/share/pcalendar/events/lunar.php');
        require('/usr/share/pcalendar/events/persian.php');
        
        $ts = persian_calendar::mktime(0, 0, 0, $month, $day, $year);
        
        // find today has an event or not? / is it holiday or not?
        $today['day'] = persian_calendar::date('z', $ts);
        $today['title'] = '';
        $today['holiday'] = false;
        foreach($l_events as $e){
            if($e['day'] == $today['day']){
                $today = $e;
            }
        }
        
        foreach($s_events as $e){
            if($e['day'] == $today['day']){
                if(isset($today['holiday']) && $today['holiday'] == false) $today['holiday'] = $e['holiday'];
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
        $l = new GtkLabel('', true);
        $l->set_use_markup(true);
        $l->set_justify(Gtk::JUSTIFY_CENTER);
        $l->set_padding(5, 5);
        $this->_leftmenu->get_child()->pack_start($l, true, true, 0);
        $str = '<b>' . persian_calendar::date('Y/m/d') . '</b>';
        $l->set_markup($str);
        
        $this->_table = new GtkTable(1, 1, true);
        $this->_table->set_col_spacings(0);
        $this->_table->set_row_spacings(0);
        $this->_leftmenu->get_child()->pack_start($this->_table, true, true, 0);
        
        $week_names = array('شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه');
        
        // fill changing months and years panel
        // left of month
        $i = GtkImage::new_from_stock(Gtk::STOCK_GO_BACK, Gtk::ICON_SIZE_MENU);
        $b = new GtkButton('');
        $b->set_can_focus(false);
        $b->set_image($i);
        $b->connect_simple('clicked', array($this, 'notify'), 'تست', 'تست');
        $this->_table->attach($b, 0, 1, 0, 1);

        // right of month
        $i = GtkImage::new_from_stock(Gtk::STOCK_GO_FORWARD, Gtk::ICON_SIZE_MENU);
        $b = new GtkButton('');
        $b->set_can_focus(false);
        $b->set_image($i);
        $b->connect_simple('clicked', array($this, 'notify'), 'تست', 'تست');
        $this->_table->attach($b, 3, 4, 0, 1);
        
        // left of year
        $i = GtkImage::new_from_stock(Gtk::STOCK_GO_BACK, Gtk::ICON_SIZE_MENU);
        $b = new GtkButton('');
        $b->set_can_focus(false);
        $b->set_image($i);
        $b->connect_simple('clicked', array($this, 'notify'), 'تست', 'تست');
        $this->_table->attach($b, 10, 11, 0, 1);

        // right of year
        $i = GtkImage::new_from_stock(Gtk::STOCK_GO_FORWARD, Gtk::ICON_SIZE_MENU);
        $b = new GtkButton('');
        $b->set_can_focus(false);
        $b->set_image($i);
        $b->connect_simple('clicked', array($this, 'notify'), 'تست', 'تست');
        $this->_table->attach($b, 13, 14, 0, 1);

        // fill month label
        $b = new GtkLabel('');
        $b->set_use_markup(true);
        $b->set_markup('آبان');
        $this->_table->attach($b, 1, 3, 0, 1);

        // fill year label
        $b = new GtkLabel('');
        $b->set_use_markup(true);
        $b->set_markup('۱۳۸۹');
        $this->_table->attach($b, 11, 13, 0, 1);
        
        // fill week names
        for($d=0; $d<7; $d++){
            $b = new GtkLabel('');
            $b->set_use_markup(true);
            $b->set_markup($week_names[$d]);
            $this->_table->attach($b, abs($d-6)*2, abs($d-6)*2+2, 1, 2);
        }
        
        // fill days
        $days = array();
        $y = 2;
        $max_days = persian_calendar::date('t', persian_calendar::mktime(0, 0, 0, $month, 1, $year), false);
        
        for($d=1; $d<=$max_days; $d++){
            $weekday = persian_calendar::date('N', persian_calendar::mktime(0, 0, 0, $month, $d, $year), false);
            $days[$d] = array('x' => abs($weekday-7), 'y' => $y);
            $today = $this->getEvent($year, $month, $d);
            $days[$d] = array_merge($days[$d], $today);
            
            $b = new GtkToggleButton('');
            $b->set_can_focus(false);
            
            if($days[$d]['holiday']){
                $b->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#FFEEEE')); // holiday
                $b->modify_bg(Gtk::STATE_ACTIVE, GdkColor::parse('#FFDDDD')); // holiday
                $b->modify_bg(Gtk::STATE_PRELIGHT, GdkColor::parse('#FFFEFE')); // holiday / hover
            } else {
                $b->modify_bg(Gtk::STATE_NORMAL, GdkColor::parse('#EEEEFF')); // normal day
                $b->modify_bg(Gtk::STATE_ACTIVE, GdkColor::parse('#DDDDFF')); // normal day
                $b->modify_bg(Gtk::STATE_PRELIGHT, GdkColor::parse('#FEFEFF')); // normal day / hover
            }
            
            $b->connect_simple('toggled', array($this, 'notify'), 'تست', 'تست');
            $b->get_child()->set_use_markup(true);
            $b->get_child()->set_markup(persian_calendar::persian_no($d) . "  <span color=\"darkgray\"><small><small>$d</small></small></span>");
            if($day == $d){
                $b->set_active(true);
            }
            $this->_table->attach($b, $days[$d]['x'] * 2, $days[$d]['x']*2+2, $days[$d]['y'], $days[$d]['y']+1);
            
            // change Y after friday!
            if($weekday == 7) $y++;
        }

        // fill event title
        $l = new GtkLabel('', true);
        $l->set_use_markup(true);
        $l->set_justify(Gtk::JUSTIFY_CENTER);
        $this->_leftmenu->get_child()->pack_start($l, true, true, 0);
        $l->modify_bg(Gtk::STATE_ACTIVE, GdkColor::parse('#FFCCCC')); // holiday
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
        $quit = new GtkMenuItem('Quit');
        $quit->connect('activate', array($this, 'onQuit'));
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

        $vbox = new GtkVBox();
        $this->_leftmenu->add($vbox);

        $this->renderCalendar(persian_calendar::date('Y', '', false), persian_calendar::date('m', '', false), persian_calendar::date('d', '', false));
    }

    public function __destruct()
    {
        Gtk::main_quit();
    }

    public function onQuit()
    {
        $this->__destruct();
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
            '/usr/share/pcalendar/cal.png', $title, $body, // app_icon, summary, body
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
