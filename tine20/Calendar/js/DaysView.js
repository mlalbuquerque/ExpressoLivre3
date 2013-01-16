/* 
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.Calendar');

/**
 * @namespace   Tine.Calendar
 * @class       Tine.Calendar.DaysView
 * @extends     Ext.util.Observable
 * Calendar view representing each day in a column
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @constructor
 * @param {Object} config
 */
Tine.Calendar.DaysView = function(config){
    Ext.apply(this, config);
    Tine.Calendar.DaysView.superclass.constructor.call(this);
    
    this.addEvents(
        /**
         * @event click
         * fired if an event got clicked
         * @param {Tine.Calendar.Model.Event} event
         * @param {Ext.EventObject} e
         */
        'click',
        /**
         * @event contextmenu
         * fired if an event got contextmenu 
         * @param {Ext.EventObject} e
         */
        'contextmenu',
        /**
         * @event dblclick
         * fired if an event got dblclicked
         * @param {Tine.Calendar.Model.Event} event
         * @param {Ext.EventObject} e
         */
        'dblclick',
        /**
         * @event changeView
         * fired if user wants to change view
         * @param {String} requested view name
         * @param {mixed} start param of requested view
         */
        'changeView',
        /**
         * @event changePeriod
         * fired when period changed
         * @param {Object} period
         */
        'changePeriod',
        /**
         * @event addEvent
         * fired when a new event got inserted
         * 
         * @param {Tine.Calendar.Model.Event} event
         */
        'addEvent',
        /**
         * @event updateEvent
         * fired when an event go resised/moved
         * 
         * @param {Tine.Calendar.Model.Event} event
         */
        'updateEvent'
    );
};

Ext.extend(Tine.Calendar.DaysView, Ext.util.Observable, {
    /**
     * @cfg {Date} startDate
     * start date
     */
    startDate: new Date(),
    /**
     * @cfg {Number} numOfDays
     * number of days to display
     */
    numOfDays: 4,
    /**
     * @cfg {String} newEventSummary
     * _('New Event')
     */
    newEventSummary: 'New Event',
    /**
     * @cfg {String} dayFormatString
     * _('{0}, the {1}. of {2}')
     */
    dayFormatString: '{0}, the {1}. of {2}',
    /**
     * @cfg {Number} timeGranularity
     * granularity of timegrid in minutes
     */
    timeGranularity: 30,
    /**
     * @cfg {Number} granularityUnitHeights
     * heights in px of a granularity unit
     */
    granularityUnitHeights: 18,
    /**
     * @cfg {Boolean} denyDragOnMissingEditGrant
     * deny drag action if edit grant for event is missing
     */
    denyDragOnMissingEditGrant: true,
    /**
     * store holding timescale
     * @type {Ext.data.Store}
     */
    timeScale: null,
    /**
     * The amount of space to reserve for the scrollbar (defaults to 19 pixels)
     * @type {Number}
     */
    scrollOffset: 19,
    /**
     * @property {bool} editing
     * @private
     */
    editing: false,
    /**
     * currently active event
     * $type {Tine.Calendar.Model.Event}
     */
    activeEvent: null,
    /**
     * @property {Ext.data.Store}
     * @private
     */
    ds: null,
    
    /**
     * updates period to display
     * @param {Array} period
     */
    updatePeriod: function(period) {
        this.toDay = new Date().clearTime();
        
        this.startDate = period.from;
        
        var tbar = this.calPanel.getTopToolbar();
        if (tbar) {
            tbar.periodPicker.update(this.startDate);
            this.startDate = tbar.periodPicker.getPeriod().from;
        }
        
        this.endDate = this.startDate.add(Date.DAY, this.numOfDays+1);
        
        //this.parallelScrollerEventsRegistry = new Tine.Calendar.ParallelEventsRegistry({dtStart: this.startDate, dtEnd: this.endDate});
        //this.parallelWholeDayEventsRegistry = new Tine.Calendar.ParallelEventsRegistry({dtStart: this.startDate, dtEnd: this.endDate});
        //this.ds.each(this.removeEvent, this);
        
        this.updateDayHeaders();
        this.onBeforeScroll();
        
        this.fireEvent('changePeriod', period);
    },
    
    /**
     * init this view
     * 
     * @param {Tine.Calendar.CalendarPanel} calPanel
     */
    init: function(calPanel) {
        this.calPanel = calPanel;
        
        this.app = Tine.Tinebase.appMgr.get('Calendar');
        
        this.newEventSummary      =  this.app.i18n._hidden(this.newEventSummary);
        this.dayFormatString      =  this.app.i18n._hidden(this.dayFormatString);
        
        this.startDate.setHours(0);
        this.startDate.setMinutes(0);
        this.startDate.setSeconds(0);
        
        this.endDate = this.startDate.add(Date.DAY, this.numOfDays+1);
        
        this.parallelScrollerEventsRegistry = new Tine.Calendar.ParallelEventsRegistry({dtStart: this.startDate, dtEnd: this.endDate});
        this.parallelWholeDayEventsRegistry = new Tine.Calendar.ParallelEventsRegistry({dtStart: this.startDate, dtEnd: this.endDate});
        
        this.initData(calPanel.store);
        
        this.initTimeScale();
        this.initTemplates();
        
        this.calPanel.on('beforehide', this.onBeforeHide, this);
        this.calPanel.on('show', this.onShow, this);
        
        Tine.Tinebase.appMgr.on('activate', this.onAppActivate, this);
    },
    
    /**
     * @private
     * @param {Ext.data.Store} ds
     */
    initData : function(ds){
        if(this.ds){
            this.ds.un("load", this.onLoad, this);
            this.ds.un("datachanged", this.onDataChange, this);
            this.ds.un("add", this.onAdd, this);
            this.ds.un("remove", this.onRemove, this);
            this.ds.un("update", this.onUpdate, this);
            this.ds.un("clear", this.onClear, this);
        }
        if(ds){
            ds.on("load", this.onLoad, this);
            ds.on("datachanged", this.onDataChange, this);
            ds.on("add", this.onAdd, this);
            ds.on("remove", this.onRemove, this);
            ds.on("update", this.onUpdate, this);
            ds.on("clear", this.onClear, this);
        }
        this.ds = ds;
    },
    
    /**
     * inits time scale
     * @private
     */
    initTimeScale: function() {
        var data = [];
        var scaleSize = Date.msDAY/(this.timeGranularity * Date.msMINUTE);
        var baseDate = this.startDate.clone();
        
        var minutes;
        for (var i=0; i<scaleSize; i++) {
            minutes = i * this.timeGranularity;
            data.push([i, minutes, minutes * Date.msMINUTE, baseDate.add(Date.MINUTE, minutes).format('H:i')]);
        }
        
        this.timeScale = new Ext.data.SimpleStore({
            fields: ['index', 'minutes', 'milliseconds', 'time'],
            data: data,
            id: 'index'
        });
    },
    
    initDropZone: function() {
        this.dd = new Ext.dd.DropZone(this.mainWrap.dom, {
            ddGroup: 'cal-event',
            
            notifyOver : function(dd, e, data) {
                var sourceEl = Ext.fly(data.sourceEl);
                sourceEl.setStyle({'border-style': 'dashed'});
                sourceEl.setOpacity(0.5);
                
                if (data.event) {
                    var event = data.event;
                    
                    // we dont support multiple dropping yet
                    data.scope.getSelectionModel().select(event);
                
                    var targetDateTime = Tine.Calendar.DaysView.prototype.getTargetDateTime.call(data.scope, e);
                    if (targetDateTime) {
                        var dtString = targetDateTime.format(targetDateTime.is_all_day_event ? Ext.form.DateField.prototype.format : 'H:i');
                        if (! event.data.is_all_day_event) {
                            Ext.fly(dd.proxy.el.query('div[class=cal-daysviewpanel-event-header-inner]')[0]).update(dtString);
                        }
                        
                        if (event.get('editGrant')) {
                            return Math.abs(targetDateTime.getTime() - event.get('dtstart').getTime()) < Date.msMINUTE ? 'cal-daysviewpanel-event-drop-nodrop' : 'cal-daysviewpanel-event-drop-ok';
                        }
                    }
                }
                
                return 'cal-daysviewpanel-event-drop-nodrop';
            },
            
            notifyOut : function() {
                //console.log('notifyOut');
                //delete this.grid;
            },
            
            notifyDrop : function(dd, e, data) {
                var v = data.scope;
                
                var targetDate = v.getTargetDateTime(e);
                
                if (targetDate) {
                    var event = data.event;
                    
                    // deny drop for missing edit grant or no time change
                    if (! event.get('editGrant') || Math.abs(targetDate.getTime() - event.get('dtstart').getTime()) < Date.msMINUTE) {
                        return false;
                    }
                    
                    event.beginEdit();
                    var originalDuration = (event.get('dtend').getTime() - event.get('dtstart').getTime()) / Date.msMINUTE;
                    
                    event.set('dtstart', targetDate);
                    
                    if (! event.get('is_all_day_event') && targetDate.is_all_day_event && event.duration < Date.msDAY) {
                        // draged from scroller -> dropped to allDay and duration less than a day
                        event.set('dtend', targetDate.add(Date.DAY, 1));
                    } else if (event.get('is_all_day_event') && !targetDate.is_all_day_event) {
                        // draged from allDay -> droped to scroller will be resetted to hone hour
                        event.set('dtend', targetDate.add(Date.HOUR, 1));
                    } else {
                        event.set('dtend', targetDate.add(Date.MINUTE, originalDuration));
                    }
                    
                    event.set('is_all_day_event', targetDate.is_all_day_event);
                    event.endEdit();
                    
                    v.fireEvent('updateEvent', event);
                }
                
                return !!targetDate;
            }
        });
    },
    
    /**
     * @private
     */
    initDragZone: function() {
        this.scroller.ddScrollConfig = {
            vthresh: this.granularityUnitHeights * 2,
            increment: this.granularityUnitHeights * 4,
            hthresh: -1,
            frequency: 500
        };
        Ext.dd.ScrollManager.register(this.scroller);
        
        // init dragables
        this.dragZone = new Ext.dd.DragZone(this.el, {
            ddGroup: 'cal-event',
            view: this,
            scroll: false,
            containerScroll: true,
            
            getDragData: function(e) {
                var selected = this.view.getSelectionModel().getSelectedEvents();
                
                var eventEl = e.getTarget('div.cal-daysviewpanel-event', 10);
                if (eventEl) {
                    var parts = eventEl.id.split(':');
                    var event = this.view.ds.getById(parts[1]);
                    
                    // don't allow dragging of dirty events
                    // don't allow dragging with missing edit grant
                    if (! event || event.dirty || (this.view.denyDragOnMissingEditGrant && ! event.get('editGrant'))) {
                        return;
                    }
                    
                    // we need to clone an event with summary in
                    var d = Ext.get(event.ui.domIds[0]).dom.cloneNode(true);
                    d.id = Ext.id();
                    
                    if (event.get('is_all_day_event')) { 
                        Ext.fly(d).setLeft(0);
                    } else {
                        var width = (Ext.fly(this.view.dayCols[0]).getWidth() * 0.9);
                        Ext.fly(d).setTop(0);
                        Ext.fly(d).setWidth(width);
                        Ext.fly(d).setHeight(this.view.getTimeHeight.call(this.view, event.get('dtstart'), event.get('dtend')));
                    }
                    
                    return {
                        scope: this.view,
                        sourceEl: eventEl,
                        event: event,
                        ddel: d,
                        selections: this.view.getSelectionModel().getSelectedEvents()
                    }
                }
            },
            
            getRepairXY: function(e, dd) {
                Ext.fly(this.dragData.sourceEl).setStyle({'border-style': 'solid'});
                Ext.fly(this.dragData.sourceEl).setOpacity(1, 1);
                
                return Ext.fly(this.dragData.sourceEl).getXY();
            }
        });
    },
    
    /**
     * renders the view
     */
    render: function() {
        this.templates.master.append(this.calPanel.body, {
            header: this.templates.header.applyTemplate({
                daysHeader: this.getDayHeaders(),
                wholeDayCols: this.getWholeDayCols()
            }),
            body: this.templates.body.applyTemplate({
                timeRows: this.getTimeRows(),
                dayColumns: this.getDayColumns()
            })
        });
        
        this.initElements();
        this.getSelectionModel().init(this);
    },
    
    /**
     * fill the events into the view
     */
    afterRender: function() {
        
        this.mainWrap.on('click', this.onClick, this);
        this.mainWrap.on('dblclick', this.onDblClick, this);
        this.mainWrap.on('contextmenu', this.onContextMenu, this);
        this.mainWrap.on('mousedown', this.onMouseDown, this);
        this.mainWrap.on('mouseup', this.onMouseUp, this);
        this.calPanel.on('resize', this.onResize, this);
        
        this.initDropZone();
        this.initDragZone();
        
        this.updatePeriod({from: this.startDate});
        
        if (this.dsLoaded) {
            this.onLoad.apply(this);
        }
        
        // scrollTo initial position
        this.isScrolling = true;
        try {
            var startTimeString = this.app.getRegistry().get('preferences').get('daysviewstarttime');
            var startTime = Date.parseDate(startTimeString, 'H:i');
            if (! Ext.isDate(startTime)) {
                throw new Ext.Error('no valid startime given');
            }
            
            this.scrollTo(startTime)
        } catch (e) {
            this.scrollTo();
        }
        
        this.layout();
        this.rendered = true;
    },
    
    scrollTo: function(time) {
        time = Ext.isDate(time) ? time : new Date();
        this.scroller.dom.scrollTop = this.getTimeOffset(time);
    },
    
    onBeforeScroll: function() {
        if (! this.isScrolling) {
            this.isScrolling = true;
            
            // walk all cols an hide hints
            Ext.each(this.dayCols, function(dayCol, idx) {
                var dayColEl  = Ext.get(dayCol),
                    aboveHint = dayColEl.down('img[class=cal-daysviewpanel-body-daycolumn-hint-above]'),
                    belowHint = dayColEl.down('img[class=cal-daysviewpanel-body-daycolumn-hint-below]');
                    
                aboveHint.setDisplayed(false);
                belowHint.setDisplayed(false);
            }, this);
        }
    },
    
    /**
     * add hint if events are outside visible area
     * 
     * @param {} e
     * @param {} t
     * @param {} o
     */
    onScroll: function(e, t, o) {
        var visibleHeight = this.scroller.dom.clientHeight,
            visibleStart  = this.scroller.dom.scrollTop,
            visibleEnd    = visibleStart + visibleHeight,
            aboveCols     = [],
            belowCols     = [];
            
        this.ds.each(function(event) {
            if (event.ui) {
                Ext.each(event.ui.domIds, function(domId) {
                    var el = Ext.get(domId),
                        box = el.getBox(false, true);
                        
                    if (box.bottom <= visibleStart) {
//                        console.log(domId + ' is above visible area');
                        aboveCols.push(el.up('div[class^=cal-daysviewpanel-body-daycolumn]'));
                    } else if (box.bottom - box.height >= visibleEnd) {
//                        console.log(domId + ' is below visible area');
                        belowCols.push(el.up('div[class^=cal-daysviewpanel-body-daycolumn]'));
                    }
                }, this);
            }
        });
        
        // walk all cols an update hints
        Ext.each(this.dayCols, function(dayCol, idx) {
            var dayColEl  = Ext.get(dayCol),
                aboveHint = dayColEl.down('img[class=cal-daysviewpanel-body-daycolumn-hint-above]'),
                belowHint = dayColEl.down('img[class=cal-daysviewpanel-body-daycolumn-hint-below]');
                
            if (aboveCols.indexOf(dayColEl) >= 0) {
                aboveHint.setTop(visibleStart + 5);
                if (!aboveHint.isVisible()) {
                    aboveHint.fadeIn({duration: 1.6});
                }
            }
            
            if (belowCols.indexOf(dayColEl) >= 0) {
                belowHint.setTop(visibleEnd - 14);
                if (!belowHint.isVisible()) {
                    belowHint.fadeIn({duration: 1.6});
                }
            }
        }, this);
        
        this.isScrolling = false;
    },
    
    onShow: function() {
        this.layout();
        this.scroller.dom.scrollTop = this.lastScrollPos || this.getTimeOffset(new Date());
    },
    
    onBeforeHide: function() {
        this.lastScrollPos = this.scroller.dom.scrollTop;
    },
    
    /**
     * renders a single event into this daysview
     * @param {Tine.Calendar.Model.Event} event
     * 
     * @todo Add support vor Events spanning over a day boundary
     */
    insertEvent: function(event) {
        event.ui = new Tine.Calendar.DaysViewEventUI(event);
        event.ui.render(this);
    },
    
    /**
     * removes all events from dom
     */
    removeAllEvents: function() {
        var els = Ext.DomQuery.select('div[class^=cal-daysviewpanel-event]', this.mainWrap.dom);
        for (var i=0; i<els.length; i++) {
            Ext.fly(els[i]).remove();
        }
        
        this.ds.each(function(event) {
            if (event.ui) {
                event.ui.domIds = [];
            }
        });
    },
    
    /**
     * removes a event from the dom
     * @param {Tine.Calendar.Model.Event} event
     */
    removeEvent: function(event) {
        if (event == this.activeEvent) {
            this.activeEvent = null;
        }
        
        if (event.ui) {
            event.ui.remove();
        }
    },
    
    /**
     * sets currentlcy active event
     * 
     * NOTE: active != selected
     * @param {Tine.Calendar.Model.Event} event
     */
    setActiveEvent: function(event) {
        this.activeEvent = event || null;
    },
    
    /**
     * gets currentlcy active event
     * 
     * @return {Tine.Calendar.Model.Event} event
     */
    getActiveEvent: function() {
        return this.activeEvent;
    },
    
    /**
     * returns the selectionModel of the active panel
     * @return {}
     */
    getSelectionModel: function() {
        return this.calPanel.getSelectionModel();
    },
    
    /**
     * creates a new event directly from this view
     * @param {} event
     */
    createEvent: function(e, event) {
        
        // only add range events if mouse is down long enough
        if (this.editing || (event.isRangeAdd && ! this.mouseDown)) {
            return;
        }
        
        // insert event silently into store
        this.editing = event;
        this.ds.suspendEvents();
        this.ds.add(event);
        this.ds.resumeEvents();
        
        
        // draw event
        var registry = event.get('is_all_day_event') ? this.parallelWholeDayEventsRegistry : this.parallelScrollerEventsRegistry;
        registry.register(event);
        this.insertEvent(event);
        //this.setActiveEvent(event);
        this.layout();
        
        //var eventEls = event.ui.getEls();
        //eventEls[0].setStyle({'border-style': 'dashed'});
        //eventEls[0].setOpacity(0.5);
        
        // start sizing for range adds
        if (event.isRangeAdd) {
            // don't create events with very small duration
            event.ui.resizeable.on('resize', function() {
                if (event.get('is_all_day_event')) {
                    var keep = true;
                } else {
                    var keep = (event.get('dtend').getTime() - event.get('dtstart').getTime()) / Date.msMINUTE >= this.timeGranularity;
                }
                
                if (keep) {
                    this.startEditSummary(event);
                } else {
                    this.abortCreateEvent(event);
                }
            }, this);
            
            var rzPos = event.get('is_all_day_event') ? 'east' : 'south';
            
            if (Ext.isIE) {
                e.browserEvent = {type: 'mousedown'};
            }
            
            event.ui.resizeable[rzPos].onMouseDown.call(event.ui.resizeable[rzPos], e);
            //event.ui.resizeable.startSizing.defer(2000, event.ui.resizeable, [e, event.ui.resizeable[rzPos]]);
        } else {
            this.startEditSummary(event);
        }
    },
    
    abortCreateEvent: function(event) {
        this.ds.remove(event);
        this.editing = false;
    },
    
    startEditSummary: function(event) {
        if (event.summaryEditor) {
            return false;
        }
        
        var eventEls = event.ui.getEls();
        
        var bodyCls = event.get('is_all_day_event') ? 'cal-daysviewpanel-wholedayevent-body' : 'cal-daysviewpanel-event-body';
        event.summaryEditor = new Ext.form.TextArea({
            event: event,
            renderTo: eventEls[0].down('div[class=' + bodyCls + ']'),
            width: event.ui.getEls()[0].getWidth() -12,
            height: Math.max(12, event.ui.getEls()[0].getHeight() -18),
            style: 'background-color: transparent; background: 0: border: 0; position: absolute; top: 0px;',
            value: this.newEventSummary,
            maxLength: 255,
            maxLengthText: this.app.i18n._('The summary must not be longer than 255 characters.'),
            minLength: 1,
            minLengthText: this.app.i18n._('The summary must have at least 1 character.'),
            enableKeyEvents: true,
            listeners: {
                scope: this,
                render: function(field) {
                    field.focus(true, 100);
                },
                blur: this.endEditSummary,
                specialkey: this.endEditSummary,
                keydown: this.endEditSummary
            }
            
        });
    },
    
    endEditSummary: function(field, e) {
        var event   = field.event;
        var summary = field.getValue();

        if (! this.editing || this.validateMsg || !Ext.isDefined(e)) {
            return;
        }

        // abort edit on ESC key
        if (e && (e.getKey() == e.ESC)) {
            this.abortCreateEvent(event);
            return;
        }

        // only commit edit on Enter & blur
        if (e && e.getKey() != e.ENTER) {
            return;
        }

        // Validate Summary maxLength
        if (summary.length > field.maxLength) {
            field.markInvalid();
            this.validateMsg = Ext.Msg.alert(this.app.i18n._('Summary too Long'), field.maxLengthText, function(){
                field.focus();
                this.validateMsg = false;
                }, this);
            return;
        }

        // Validate Summary minLength
        if (!summary || summary.length < field.minLength) {
            field.markInvalid();
            this.validateMsg = Ext.Msg.alert(this.app.i18n._('Summary too Short'), field.minLengthText, function(){
                field.focus();
                this.validateMsg = false;
                }, this);
            return;
        }

        this.editing = false;
        event.summaryEditor = false;
        
        event.set('summary', summary);
        
        this.ds.suspendEvents();
        this.ds.remove(event);
        this.ds.resumeEvents();
        
        var registry = event.get('is_all_day_event') ? this.parallelWholeDayEventsRegistry : this.parallelScrollerEventsRegistry;
        registry.unregister(event);
        this.removeEvent(event);
        
        event.dirty = true;
        this.ds.add(event);
        this.fireEvent('addEvent', event);

        //this.ds.resumeEvents();
        //this.ds.fireEvent.call(this.ds, 'add', this.ds, [event], this.ds.indexOf(event));
    },
    
    onAppActivate: function(app) {
        if (app === this.app) {
            // get Preference
//            try {
//                var startTimeString = this.app.getRegistry().get('preferences').get('daysviewstarttime');
//                var startTime = Date.parseDate(startTimeString, 'H:i');
//                if (! Ext.isDate(startTime)) {
//                    throw new Ext.Error('no valid startime given');
//                }
//                
//                this.scroller.dom.scrollTop = this.getTimeOffset(startTime);
//            } catch (e) {
//                this.scrollToNow();
//            }
        }
    },
    
    onResize: function(e) {
        // redraw whole day events
        (function(){this.ds.each(function(event) {
            if (event.get('is_all_day_event')) {
                this.removeEvent(event);
                this.insertEvent(event);
            }
        }, this)}).defer(50, this);
        
    },
    
    onClick: function(e) {
        // check for hint clicks first
        var hint = e.getTarget('img[class^=cal-daysviewpanel-body-daycolumn-hint-]', 10, true);
        if (hint) {
            this.scroller.scroll(hint.hasClass('cal-daysviewpanel-body-daycolumn-hint-above') ? 't' : 'b', 10000, true);
            return;
        }
        
        var event = this.getTargetEvent(e);
        if (event) {
            this.fireEvent('click', event, e);
        }
    },
    
    onContextMenu: function(e) {
        this.fireEvent('contextmenu', e);
    },
    
    /**
     * @private
     */
    onDblClick: function(e, target) {
        e.stopEvent();
        var event = this.getTargetEvent(e);
        var dtStart = this.getTargetDateTime(e);
        
        if (event) {
            this.fireEvent('dblclick', event, e);
        } else if (dtStart && !this.editing) {
            var newId = 'cal-daysviewpanel-new-' + Ext.id();
            var dtend = dtStart.add(Date.HOUR, 1);
            if (dtStart.is_all_day_event) {
                dtend = dtend.add(Date.HOUR, 23).add(Date.SECOND, -1);
            }
            
            var event = new Tine.Calendar.Model.Event(Ext.apply(Tine.Calendar.Model.Event.getDefaultData(), {
                id: newId,
                dtstart: dtStart, 
                dtend: dtend,
                is_all_day_event: dtStart.is_all_day_event
            }), newId);
            
            this.createEvent(e, event);
            event.dirty = true;
        } else if (target.className == 'cal-daysviewpanel-dayheader-day'){
            var dayHeaders = Ext.DomQuery.select('div[class=cal-daysviewpanel-dayheader-day]', this.innerHd);
            var date = this.startDate.add(Date.DAY, dayHeaders.indexOf(target));
            this.fireEvent('changeView', 'day', date);
        }
    },
    
    /**
     * @private
     */
    onMouseDown: function(e) {
        // only care for left mouse button
        if (e.button !== 0) {
            return;
        }
        
        if (! this.editing) {
            this.focusEl.focus();
        }
        this.mouseDown = true;
        
        var targetEvent = this.getTargetEvent(e);
        if (this.editing && this.editing.summaryEditor && (targetEvent != this.editing)) {
            this.editing.summaryEditor.fireEvent('blur', this.editing.summaryEditor, null);
        }

        var sm = this.getSelectionModel();
        sm.select(targetEvent);
        
        var dtStart = this.getTargetDateTime(e);
        if (dtStart) {
            var newId = 'cal-daysviewpanel-new-' + Ext.id();
            var event = new Tine.Calendar.Model.Event(Ext.apply(Tine.Calendar.Model.Event.getDefaultData(), {
                id: newId,
                dtstart: dtStart, 
                dtend: dtStart.is_all_day_event ? dtStart.add(Date.HOUR, 24).add(Date.SECOND, -1) : dtStart.add(Date.MINUTE, 2*this.timeGranularity/2),
                is_all_day_event: dtStart.is_all_day_event
            }), newId);
            event.isRangeAdd = true;
            event.dirty = true;
            
            e.stopEvent();
            this.createEvent.defer(100, this, [e, event]);
        }
    },
    
    /**
     * @private
     */
    onMouseUp: function() {
        this.mouseDown = false;
    },
    
    /**
     * @private
     */
    onBeforeEventResize: function(rz, e) {
        var parts = rz.el.id.split(':');
        var event = this.ds.getById(parts[1]);
        
        rz.event = event;
        rz.originalHeight = rz.el.getHeight();
        rz.originalWidth  = rz.el.getWidth();

        // NOTE: ext dosn't support move events via api
        rz.onMouseMove = rz.onMouseMove.createSequence(function() {
            var event = this.event;
            if (! event) {
                //event already gone -> late event / busy brower?
                return;
            }
            var ui = event.ui;
            var rzInfo = ui.getRzInfo(this);
            
            this.durationEl.update(rzInfo.dtend.format(event.get('is_all_day_event') ? Ext.form.DateField.prototype.format : 'H:i'));
        }, rz);
        
        event.ui.markDirty();
        
        // NOTE: Ext keeps proxy if element is not destroyed (diff !=0)
        if (! rz.durationEl) {
            rz.durationEl = rz.el.insertFirst({
                'class': 'cal-daysviewpanel-event-rzduration',
                'style': 'position: absolute; bottom: 3px; right: 2px; z-index: 1000;'
            });
        }
        rz.durationEl.update(event.get('dtend').format(event.get('is_all_day_event') ? Ext.form.DateField.prototype.format : 'H:i'));
        
        if (event) {
            this.getSelectionModel().select(event);
        } else {
            this.getSelectionModel().clearSelections();
        }
    },
    
    /**
     * @private
     */
    onEventResize: function(rz, width, height) {
        var event = rz.event;
        
        if (! event) {
            //event already gone -> late event / busy brower?
            return;
        }
        
        var rzInfo = event.ui.getRzInfo(rz, width, height);
        if (rzInfo.diff != 0) {
            event.set('dtend', rzInfo.dtend);
        }
        
        if (event.summaryEditor) {
            event.summaryEditor.setHeight(event.ui.getEls()[0].getHeight() -18);
        }
        
        // don't fire update events on rangeAdd
        if (rzInfo.diff != 0 && event != this.editing && ! event.isRangeAdd) {
            this.fireEvent('updateEvent', event);
        } else {
            event.ui.clearDirty();
        }
    },
    
    /**
     * @private
     */
    onDataChange : function(){
        //console.log('onDataChange');
        //this.refresh();
    },

    /**
     * @private
     */
    onClear : function(){
        //console.log('onClear')
        //this.refresh();
    },

    /**
     * @private
     */
    onUpdate : function(ds, event){
        // don't update events while being created
        if (event.get('id').match(/new/)) {
            return;
        }
        
        // relayout original context
        var originalRegistry = (event.modified.hasOwnProperty('is_all_day_event') ? event.modified.is_all_day_event : event.get('is_all_day_event')) ? 
            this.parallelWholeDayEventsRegistry : 
            this.parallelScrollerEventsRegistry;

        var registry = event.get('is_all_day_event') ? this.parallelWholeDayEventsRegistry : this.parallelScrollerEventsRegistry;
        var originalDtstart = event.modified.hasOwnProperty('dtstart') ? event.modified.dtstart : event.get('dtstart');
        var originalDtend = event.modified.hasOwnProperty('dtend') ? event.modified.dtend : event.get('dtend');

        var originalParallels = originalRegistry.getEvents(originalDtstart, originalDtend);
        for (var j=0; j<originalParallels.length; j++) {
            this.removeEvent(originalParallels[j]);
        }
        originalRegistry.unregister(event);
        
        var originalParallels = originalRegistry.getEvents(originalDtstart, originalDtend);
        for (var j=0; j<originalParallels.length; j++) {
            this.insertEvent(originalParallels[j]);
        }
        
        // relayout actual context
        var parallelEvents = registry.getEvents(event.get('dtstart'), event.get('dtend'));
        for (var j=0; j<parallelEvents.length; j++) {
            this.removeEvent(parallelEvents[j]);
        }
        
        registry.register(event);
        var parallelEvents = registry.getEvents(event.get('dtstart'), event.get('dtend'));
        for (var j=0; j<parallelEvents.length; j++) {
            this.insertEvent(parallelEvents[j]);
        }
        
        this.setActiveEvent(this.getActiveEvent());
        this.layout();
    },

    /**
     * @private
     */
    onAdd : function(ds, records, index){
        //console.log('onAdd');
        for (var i=0; i<records.length; i++) {
            var event = records[i];
            
            var registry = event.get('is_all_day_event') ? this.parallelWholeDayEventsRegistry : this.parallelScrollerEventsRegistry;
            registry.register(event);
            
            var parallelEvents = registry.getEvents(event.get('dtstart'), event.get('dtend'));
            
            for (var j=0; j<parallelEvents.length; j++) {
                this.removeEvent(parallelEvents[j]);
                this.insertEvent(parallelEvents[j]);
            }
            
            //this.setActiveEvent(event);
        }
        
        this.layout();
    },

    /**
     * @private
     */
    onRemove : function(ds, event, index, isUpdate) {
        if (!event || index == -1) {
            return;
        }
        
        if(isUpdate !== true){
            //this.fireEvent("beforeeventremoved", this, index, record);
        }
        var registry = event.get('is_all_day_event') ? this.parallelWholeDayEventsRegistry : this.parallelScrollerEventsRegistry;
        registry.unregister(event);
        this.removeEvent(event);
        this.getSelectionModel().unselect(event);
        this.layout();
    },
    
    /**
     * @private
     */
    onLoad : function() {
        if(! this.rendered){
            this.dsLoaded = true;
            return;
        }
        
        // remove all old events from dom
        this.removeAllEvents();
        
        // setup registry
        this.parallelScrollerEventsRegistry = new Tine.Calendar.ParallelEventsRegistry({dtStart: this.startDate, dtEnd: this.endDate});
        this.parallelWholeDayEventsRegistry = new Tine.Calendar.ParallelEventsRegistry({dtStart: this.startDate, dtEnd: this.endDate});
        
        // todo: sort generic?
        this.ds.fields = Tine.Calendar.Model.Event.prototype.fields;
        this.ds.sortInfo = {field: 'dtstart', direction: 'ASC'};
        this.ds.applySort();
        
        this.ds.each(function(event) {
            var registry = event.get('is_all_day_event') ? this.parallelWholeDayEventsRegistry : this.parallelScrollerEventsRegistry;
            registry.register(event);
        }, this);
        
        // put the events in
        this.ds.each(this.insertEvent, this);
        
        this.layout();
    },
    
    /**
     * print wrapper
     */
    print: function() {
        var renderer = new Tine.Calendar.Printer.DaysViewRenderer();
        renderer.print(this);
    },
    
    hex2dec: function(hex) {
        var dec = 0;
        hex = hex.toString();
        var length = hex.length, multiplier, digit;
        for (var i=0; i<length; i++) {
            
            multiplier = Math.pow(16, (Math.abs(i - hex.length)-1));
            digit = parseInt(hex.toString().charAt([i]), 10);
            if (isNaN(digit)) {
                switch (hex.toString().charAt([i]).toUpperCase()) {
                    case 'A': digit = 10;  break;
                    case 'B': digit = 11;  break;
                    case 'C': digit = 12;  break;
                    case 'D': digit = 13;  break;
                    case 'E': digit = 14;  break;
                    case 'F': digit = 15;  break;
                    default: return NaN;
                }
            }
            dec = dec + (multiplier * digit);
        }
        
        return dec;
    },
    
    getPeriod: function() {
        return {
            from: this.startDate,
            until: this.startDate.add(Date.DAY, this.numOfDays)
        };
    },
    
    /**
     * get date of a (event) target
     * 
     * @param {Ext.EventObject} e
     * @return {Date}
     */
    getTargetDateTime: function(e) {
        var target = e.getTarget('div[class^=cal-daysviewpanel-datetime]');
        
        if (target && target.id.match(/^ext-gen\d+:\d+/)) {
            var parts = target.id.split(':');
            
            var date = this.startDate.add(Date.DAY, parseInt(parts[1], 10));
            date.is_all_day_event = true;
            
            if (parts[2] ) {
                var timePart = this.timeScale.getAt(parts[2]);
                date = date.add(Date.MINUTE, timePart.get('minutes'));
                date.is_all_day_event = false;
            }   
            return date;
        }
    },
    
    /**
     * gets event el of target
     * 
     * @param {Ext.EventObject} e
     * @return {Tine.Calendar.Model.Event}
     */
    getTargetEvent: function(e) {
        var target = e.getTarget();
        var el = Ext.fly(target);
        
        if (el.hasClass('cal-daysviewpanel-event') || (el = el.up('[id*=event:]', 10))) {
            var parts = el.dom.id.split(':');
            
            return this.ds.getById(parts[1]);
        }
    },
    
    getTimeOffset: function(date) {
        var d = this.granularityUnitHeights / this.timeGranularity;
        
        return Math.round(d * ( 60 * date.getHours() + date.getMinutes()));
    },
    
    getTimeHeight: function(dtStart, dtEnd) {
        var d = this.granularityUnitHeights / this.timeGranularity;
        return Math.round(d * ((dtEnd.getTime() - dtStart.getTime()) / Date.msMINUTE));
    },
    
    /**
     * fetches elements from our generated dom
     */
    initElements : function(){
        var E = Ext.Element;

        var el = this.calPanel.body.dom.firstChild;
        var cs = el.childNodes;

        this.el = new E(el);

        this.mainWrap = new E(cs[0]);
        this.mainHd = new E(this.mainWrap.dom.firstChild);

        this.innerHd = this.mainHd.dom.firstChild;
        
        this.wholeDayArea = this.innerHd.firstChild.childNodes[1];
        
        this.scroller = new E(this.mainWrap.dom.childNodes[1]);
        this.scroller.setStyle('overflow-x', 'hidden');
        this.scroller.on('scroll', this.onBeforeScroll, this);
        this.scroller.on('scroll', this.onScroll, this, {buffer: 200});
        
        this.mainBody = new E(this.scroller.dom.firstChild);
        
        this.dayCols = this.mainBody.dom.firstChild.lastChild.childNodes;

        this.focusEl = new E(this.el.dom.lastChild);
        this.focusEl.swallowEvent("click", true);
        this.focusEl.swallowEvent("dblclick", true);
        this.focusEl.swallowEvent("contextmenu", true);
    },
    
    /**
     * @TODO this returns wrong cols on DST boundaries:
     *  e.g. on DST switch form +2 to +1 an all day event is 25 hrs. long
     * 
     * @param {} date
     * @return {}
     */
    getColumnNumber: function(date) {
        return Math.floor((date.add(Date.SECOND, 1).getTime() - this.startDate.getTime()) / Date.msDAY);
    },
    
    getDateColumnEl: function(pos) {
        return this.dayCols[pos];
    },
    
    checkWholeDayEls: function() {
        var freeIdxs = [];
        for (var i=0; i<this.wholeDayArea.childNodes.length-1; i++) {
            if(this.wholeDayArea.childNodes[i].childNodes.length === 1) {
                freeIdxs.push(i);
            }
        }
        
        for (var i=1; i<freeIdxs.length; i++) {
            Ext.fly(this.wholeDayArea.childNodes[freeIdxs[i]]).remove();
        }
    },
    
    /**
     * layouts the view
     */
    layout: function() {
        if(!this.mainBody){
            return; // not rendered
        }
        
        var g = this.calPanel;
        var c = g.body;
        var csize = c.getSize(true);
        var vw = csize.width;
        
        this.el.setSize(csize.width, csize.height);
        
        this.layoutWholeDayHeader();
        var hdHeight = this.mainHd.getHeight();
        
        var vh = csize.height - (hdHeight);

        this.scroller.setSize(vw, vh);
        // we add 2 more pixel to have spare space for our left padding
        this.innerHd.style.width = (vw + 2)+'px';
        
        // force positioning on scroll hints
        this.onScroll.defer(100, this);
    },
    
    layoutWholeDayHeader: function() {
        var headerEl = Ext.get(this.wholeDayArea);
        
        for (var i=0, bottom = headerEl.getTop(); i<this.wholeDayArea.childNodes.length -1; i++) {
            bottom = Math.max(parseInt(Ext.get(this.wholeDayArea.childNodes[i]).getBottom(), 10), bottom);
        }
        
        headerEl.setHeight(bottom - headerEl.getTop() + 10);
    },
    
    /**
     * returns HTML frament of the day headers
     */
    getDayHeaders: function() {
        var html = '';
        var width = 100/this.numOfDays;
        
        for (var i=0, date; i<this.numOfDays; i++) {
            var day = this.startDate.add(Date.DAY, i);
            html += this.templates.dayHeader.applyTemplate({
                day: String.format(this.dayFormatString, day.format('l'), day.format('j'), day.format('F')),
                height: this.granularityUnitHeights,
                width: width + '%',
                left: i * width + '%'
            });
        }
        return html;
    },
    
    /**
     * updates HTML of day headers
     */
    updateDayHeaders: function() {
        var dayHeaders = Ext.DomQuery.select('div[class=cal-daysviewpanel-dayheader-day]', this.innerHd);
        
        for (var i=0, date, isToDay, isHolliday, headerEl, dayColEl; i<dayHeaders.length; i++) {
            
            date = this.startDate.add(Date.DAY, i);
            isToDay = date.getTime() == this.toDay.getTime();
            if(date.getFullYear() != ano || date.getMonth()+1 != mes){
            prepara_feriados(date.getFullYear(),date.getMonth()+1);
            }
            isHolliday = feriados[date.format('j')];
            VisHolliday = isHolliday ? 'Title="' + feriados[date.format('j')] + '"' : '';
            headerEl = Ext.fly(dayHeaders[i]);
            //alert(String.format(this.dayFormatString, date.format('l'), date.format('j'), date.format('F')));
            headerEl.update('<span ' + VisHolliday +'>' + String.format(this.dayFormatString, date.format('l'), date.format('j'), date.format('F')) + '</span>');
            headerEl.parent()[(isToDay ? 'add' : 'remove') + 'Class']('cal-daysviewpanel-dayheader-today');
            headerEl.parent()[(isHolliday ? 'add' : 'remove') + 'Class']('cal-daysviewpanel-dayheader-holliday');
            Ext.fly(this.dayCols[i])[(isToDay ? 'add' : 'remove') + 'Class']('cal-daysviewpanel-body-daycolumn-today');
        }
    },
    
    /**
     * returns HTML fragment of the whole day cols
     */
    getWholeDayCols: function() {
        var html = '';
        var width = 100/this.numOfDays;
        
        var baseId = Ext.id();
        for (var i=0; i<this.numOfDays; i++) {
            html += this.templates.wholeDayCol.applyTemplate({
                //day: date.get('dateString'),
                //height: this.granularityUnitHeights,
                id: baseId + ':' + i,
                width: width + '%',
                left: i * width + '%'
            });
        };
        
        return html;
    },
    
    /**
     * gets HTML fragment of the horizontal time rows
     */
    getTimeRows: function() {
        var html = '';
        this.timeScale.each(function(time){
            var index = time.get('index');
            html += this.templates.timeRow.applyTemplate({
                cls: index%2 ? 'cal-daysviewpanel-timeRow-off' : 'cal-daysviewpanel-timeRow-on',
                height: this.granularityUnitHeights + 'px',
                top: index * this.granularityUnitHeights + 'px',
                time: index%2 ? '' : time.get('time')
            });
        }, this);
        
        return html;
    },
    
    /**
     * gets HTML fragment of the day columns
     */
    getDayColumns: function() {
        var html = '';
        var width = 100/this.numOfDays;
        
        for (var i=0; i<this.numOfDays; i++) {
            html += this.templates.dayColumn.applyTemplate({
                width: width + '%',
                left: i * width + '%',
                overRows: this.getOverRows(i)
            });
        }
        
        return html;
    },
    
    /**
     * gets HTML fragment of the time over rows
     */
    getOverRows: function(dayIndex) {
        var html = '';
        var baseId = Ext.id();
        
        this.timeScale.each(function(time){
            var index = time.get('index');
            html += this.templates.overRow.applyTemplate({
                id: baseId + ':' + dayIndex + ':' + index,
                cls: 'cal-daysviewpanel-daycolumn-row-' + (index%2 ? 'off' : 'on'),
                height: this.granularityUnitHeights + 'px',
                time: time.get('time')
            });
        }, this);
        
        return html;
    },
    
    /**
     * inits all tempaltes of this view
     */
    initTemplates: function() {
        var ts = this.templates || {};
    
        ts.master = new Ext.XTemplate(
            '<div class="cal-daysviewpanel" hidefocus="true">',
                '<div class="cal-daysviewpanel-viewport">',
                    '<div class="cal-daysviewpanel-header"><div class="cal-daysviewpanel-header-inner"><div class="cal-daysviewpanel-header-offset">{header}</div></div><div class="x-clear"></div></div>',
                    '<div class="cal-daysviewpanel-scroller"><div class="cal-daysviewpanel-body">{body}</div></div>',
                '</div>',
                '<a href="#" class="cal-daysviewpanel-focus" tabIndex="-1"></a>',
            '</div>'
        );
        
        ts.header = new Ext.XTemplate(
            '<div class="cal-daysviewpanel-daysheader">{daysHeader}</div>' +
            
            '<div class="cal-daysviewpanel-wholedayheader">' +
                '<div class="cal-daysviewpanel-wholedayheader-daycols">{wholeDayCols}</div>' +
            '</div>'
        );
        
        ts.dayHeader = new Ext.XTemplate(
            '<div class="cal-daysviewpanel-dayheader" style="height: {height}; width: {width}; left: {left};">' + 
                '<div class="cal-daysviewpanel-dayheader-day-wrap">' +
                    '<div class="cal-daysviewpanel-dayheader-day">{day}</div>' +
                '</div>',
            '</div>'
        );
        
        ts.wholeDayCol = new Ext.XTemplate(
            '<div class="cal-daysviewpanel-body-wholedaycolumn" style="left: {left}; width: {width};">' +
                '<div id="{id}" class="cal-daysviewpanel-datetime cal-daysviewpanel-body-wholedaycolumn-over">&#160;</div>' +
            '</div>'
        );
        
        ts.body = new Ext.XTemplate(
            '<div class="cal-daysviewpanel-body-inner">' +
                '{timeRows}' +
                '<div class="cal-daysviewpanel-body-daycolumns">{dayColumns}</div>' +
            '</div>'
        );
        
        ts.timeRow = new Ext.XTemplate(
            '<div class="{cls}" style="height: {height}; top: {top};">',
                '<div class="cal-daysviewpanel-timeRow-time">{time}</div>',
            '</div>'
        );
        
        ts.dayColumn = new Ext.XTemplate(
            '<div class="cal-daysviewpanel-body-daycolumn" style="left: {left}; width: {width};">',
                '<div class="cal-daysviewpanel-body-daycolumn-inner">&#160;</div>',
                '{overRows}',
                '<img src="', Ext.BLANK_IMAGE_URL, '" class="cal-daysviewpanel-body-daycolumn-hint-above" />',
                '<img src="', Ext.BLANK_IMAGE_URL, '" class="cal-daysviewpanel-body-daycolumn-hint-below" />',
            '</div>'
        );
        
        ts.overRow = new Ext.XTemplate(
            '<div id="{id}" class="cal-daysviewpanel-datetime cal-daysviewpanel-daycolumn-row" style="height: {height};">' +
                '<div class="{cls}" >{time}</div>'+
            '</div>'
        );
        
        ts.event = new Ext.XTemplate(
            '<div id="{id}" class="cal-daysviewpanel-event {extraCls}" style="width: {width}; height: {height}; left: {left}; top: {top}; z-index: {zIndex}; background-color: {bgColor}; border-color: {color};">' +
                '<div class="cal-daysviewpanel-event-header" style="background-color: {color};">' +
                    '<div class="cal-daysviewpanel-event-header-inner" style="color: {textColor}; background-color: {color}; z-index: {zIndex};">{startTime}</div>' +
                    '<div class="cal-daysviewpanel-event-header-icons">' +
                        '<tpl for="statusIcons">' +
                            '<img src="', Ext.BLANK_IMAGE_URL, '" class="cal-status-icon {status}-{[parent.textColor == \'#FFFFFF\' ? \'white\' : \'black\']}" ext:qtip="{text}" />',
                        '</tpl>' +
                    '</div>' +
                '</div>' +
                '<div class="cal-daysviewpanel-event-body">{[Ext.util.Format.nl2br(Ext.util.Format.htmlEncode(values.summary))]}</div>' +
            '</div>'
        );
        
        ts.wholeDayEvent = new Ext.XTemplate(
            '<div id="{id}" class="cal-daysviewpanel-event {extraCls}" style="width: {width}; height: {height}; left: {left}; top: {top}; z-index: {zIndex}; background-color: {bgColor}; border-color: {color};">' +
                '<div class="cal-daysviewpanel-wholedayevent-body">{[Ext.util.Format.nl2br(Ext.util.Format.htmlEncode(values.summary))]}</div>' +
//                '<div class="cal-daysviewpanel-wholedayevent-icons">' +
                '<div class="cal-daysviewpanel-event-header-icons" style="background-color: {bgColor};" >' +
                    '<tpl for="statusIcons">' +
                        '<img src="', Ext.BLANK_IMAGE_URL, '" class="cal-status-icon {status}-black" ext:qtip="{text}" />',
                    '</tpl>' +
                '</div>' +
            '</div>'
        );
        
        for(var k in ts){
            var t = ts[k];
            if(t && typeof t.compile == 'function' && !t.compiled){
                t.disableFormats = true;
                t.compile();
            }
        }

        this.templates = ts;
    }
});
