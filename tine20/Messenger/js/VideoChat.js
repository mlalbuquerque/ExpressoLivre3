
Ext.ns('Tine.Messenger');

const VideoChatStates = {
	IDLE : 0,
	CALL_CALLING : 1,
	CALL_RINGING : 2,
	CALL_ESTABLISHED : 3
};

Tine.Messenger.VideoChat = {
    
    state: VideoChatStates.IDLE,
    
    /**
     * Far user jid
     */
    jid: null,
    
    /**
     * Cumulus connection id
     */
    id:null,
    
    /**
     * Cumulus connection farId
     */
    farId:null,
    
    hided : true,
    
    originalChatWidth: 400,
    
    VIDEOCHAT_OBJECT_ID : 'messenger-chat-videochat-object',
    
    flashVideoWidth: 370,
    
    sendStartCall: function (jid, myId) {
	var to = typeof jid == 'string' ? jid : jid.node.attributes.jid;
                       
	var info = $msg({
	    'to': to + '/' + Tine.Tinebase.registry.get('messenger').messenger.resource,
	    'type': 'videochat'
	});
	
	info.c("startcall", {
	    'id': myId,
	    'user': Tine.Messenger.Util.getJidFromConfigNoResource()
	});
	
        Tine.Messenger.Application.connection.send(info);         
    },
    sendRejectCall: function(item){
	
	var to = typeof item == 'string' ? item : item.node.attributes.jid;
	               
	var info = $msg({
	    'to': to + '/' + Tine.Tinebase.registry.get('messenger').messenger.resource,
	    'type': 'videochat'
	});
	
	info.c("rejectcall", {
	    'user': Tine.Messenger.Util.getJidFromConfigNoResource()
	});
	
        Tine.Messenger.Application.connection.send(info);  
    },
    sendBusy: function(jid){
                       
	var info = $msg({
	    'to': jid + '/' + Tine.Tinebase.registry.get('messenger').messenger.resource,
	    'type': 'videochat'
	});
	
	info.c("busy", {
	    'user': Tine.Messenger.Util.getJidFromConfigNoResource()
	});
	
        Tine.Messenger.Application.connection.send(info);         
    },
    
    onStartCall: function(msg){
	var startcall = $(msg).find('startcall'),
		id = startcall.attr('id'),
		user = startcall.attr('user');
		
	if(Tine.Messenger.VideoChat.state == VideoChatStates.IDLE){
	    Tine.Messenger.VideoChat.state = VideoChatStates.CALL_RINGING;
	    Ext.MessageBox.confirm('', user + ' is inviting you to a VideoChat. Do you Accept'+ ' ?', function(btn) {
                    if(btn == 'yes') {
			Tine.Messenger.VideoChat.farId = id;
			Tine.Messenger.VideoChat.jid = user;
			var chat = Tine.Messenger.ChatHandler.showChatWindow(user, '', 'chat', true);
			Tine.Messenger.VideoChat.loadVideoChat(chat);
		    }
		    else{
			Tine.Messenger.VideoChat.sendRejectCall(user);
			Tine.Messenger.VideoChat.state = VideoChatStates.IDLE;
		    }
                });
	}
	else{
	    Tine.Messenger.VideoChat.sendBusy(user);
	}
	    
    },
    onRejectCall: function(msg){
	var rejectcall = $(msg).find('rejectcall'),
	    user = rejectcall.attr('user');
	    
	var chat = Tine.Messenger.VideoChat.getChatWindow(Tine.Messenger.VideoChat.jid);
	Tine.Messenger.VideoChat.unloadVideoChat(chat);
	Tine.Messenger.VideoChat.hideVideoChat(chat);
	
	var app = Tine.Tinebase.appMgr.get('Messenger');
	Tine.Messenger.ChatHandler.setChatMessage(
	    Tine.Messenger.VideoChat.jid, 
	    user + ' rejected your call', 
	    app.i18n._('Info'),
	    'messenger-notify'
	);
	Tine.Messenger.VideoChat.state = VideoChatStates.IDLE;
	
    },
    onBusy: function(msg){
	var busy = $(msg).find('busy'),
	    user = busy.attr('user');
	    
	var chat = Tine.Messenger.VideoChat.getChatWindow(Tine.Messenger.VideoChat.jid);
	Tine.Messenger.VideoChat.unloadVideoChat(chat);
	Tine.Messenger.VideoChat.hideVideoChat(chat);
	
	var app = Tine.Tinebase.appMgr.get('Messenger');
	Tine.Messenger.ChatHandler.setChatMessage(
	    Tine.Messenger.VideoChat.jid, 
	    user + ' is busy', 
	    app.i18n._('Info'),
	    'messenger-notify'
	);
	Tine.Messenger.VideoChat.state = VideoChatStates.IDLE;
	    
    },
    
    onRequest: function (msg) {
	
	if($(msg).find('startcall').length)
	    Tine.Messenger.VideoChat.onStartCall(msg);
	else if($(msg).find('rejectcall').length)
	    Tine.Messenger.VideoChat.onRejectCall(msg);
	else if($(msg).find('busy').length)
	    Tine.Messenger.VideoChat.onBusy(msg);
	    
	
	return true;

    },
   
   
   
   
// -------------------------------------------------------------------   
   
   
    startVideo: function (window_chat, id, jid){

	console.debug('begin startVideo');
	if(Tine.Messenger.VideoChat.state == VideoChatStates.IDLE){
	    Tine.Messenger.VideoChat.jid = jid;
	    Tine.Messenger.VideoChat.loadVideoChat(window_chat);
	    Tine.Messenger.VideoChat.showVideoChat(window_chat);
	    Tine.Messenger.VideoChat.state = VideoChatStates.CALL_CALLING;
	}
	else{
	     Ext.MessageBox.show({
                    title: '', 
                    msg: 'You are already in a VideoChat or already has invited someone...',
                    buttons: Ext.Msg.OK,
                    icon: Ext.MessageBox.INFO
                });
	}
	
	return true;
    },
        
   
    appLoaded: function()
    {
	console.debug('begin appLoaded');
	Tine.Messenger.VideoChat.startApp();
    },
    startApp:function(){
	console.debug('begin startApp');
	    var movie = Tine.Messenger.VideoChat.getFlashMovie(); 
	    
	    movie.startApp("rtmfp://10.200.118.61", Tine.Messenger.Util.getJidFromConfigNoResource());
	    
	    return true;
    },
    
    myId: function(id){
	console.debug('begin myId');
	Tine.Messenger.VideoChat.id = id;
	if(Tine.Messenger.VideoChat.state != VideoChatStates.CALL_RINGING){
	    Tine.Messenger.VideoChat.acceptCallFrom(Tine.Messenger.VideoChat.jid);
	    Tine.Messenger.VideoChat.state = VideoChatStates.CALL_CALLING;
	    Tine.Messenger.VideoChat.sendStartCall(Tine.Messenger.VideoChat.jid, Tine.Messenger.VideoChat.id);
	}
	else{
	    Tine.Messenger.VideoChat.placeCall(Tine.Messenger.VideoChat.farId);
	}

    },
    acceptCallFrom:function(jid){
	console.debug('begin acceptCallFrom');
	var movie = Tine.Messenger.VideoChat.getFlashMovie(); 
	movie.acceptCallFrom(jid);
    },
    placeCall: function(farId){
	console.debug('begin placeCall');
	var movie = Tine.Messenger.VideoChat.getFlashMovie(); 
	movie.placeCall('', farId);
    },
    
    callStarted: function(){
	console.debug('begin callStarted');
	
	var chat = Tine.Messenger.ChatHandler.showChatWindow(Tine.Messenger.VideoChat.jid, '', 'chat', true);
	Tine.Messenger.VideoChat.showVideoChat(chat);
	
	Tine.Messenger.VideoChat.state = VideoChatStates.CALL_ESTABLISHED;
    },
    hangup: function(_box){
	if(Tine.Messenger.VideoChat.state != VideoChatStates.IDLE){
	    if(!Tine.Messenger.VideoChat.hided)
		Tine.Messenger.VideoChat.hideVideoChat(_box);

	    var movie = Tine.Messenger.VideoChat.getFlashMovie(); 
	    if(movie)
		movie.hangup();
	}
	
	
    },
    callEnded: function(){
	
	var chat = Tine.Messenger.VideoChat.getChatWindow(Tine.Messenger.VideoChat.jid);
	
	Tine.Messenger.VideoChat.hideVideoChat(chat);
	
	if(chat != null)
	    Tine.Messenger.VideoChat.unloadVideoChat(chat);
	
		
	Tine.Messenger.VideoChat.state = VideoChatStates.IDLE;
	
    },
    
    
    
    
    
    
    getFlashMovie: function (){
	if (navigator.appName.indexOf("Microsoft") != -1) {
	    return window[Tine.Messenger.VideoChat.VIDEOCHAT_OBJECT_ID];
	} else {
	    return document[Tine.Messenger.VideoChat.VIDEOCHAT_OBJECT_ID];
	}
    },
    getChatWindow: function(jid){
	var chat_id = Tine.Messenger.ChatHandler.formatChatId(jid);
	var chat = Ext.getCmp(chat_id) ? Ext.getCmp(chat_id) : null;
	return chat;
    },
    
    
    loadVideoChat : function(_box){
	console.debug('begin loadVideoChat');

	    var flash = new Ext.FlashComponent({
		url: "Messenger/flash/ExpressoVideoChat.swf",
		wmode:"transparent",
		flashParams:{wmode:"transparent"},
		flashVars:{useExternalInterface: "true", extNamespace : "Tine.Messenger.VideoChat"},
		id: Tine.Messenger.VideoChat.VIDEOCHAT_OBJECT_ID, 
		allowScriptAccess:"sameDomain",
		flashVersion: "10.0.0",
		swfWidth: Tine.Messenger.VideoChat.flashVideoWidth,
		swfHeight: 300

	    });

	    _box.getComponent('messenger-chat-videochat').add(flash);

	    _box.doLayout();
	   console.debug('end loadVideoChat');
    
    },
    showVideoChat: function(_box){
	console.debug('begin showVideoChat');
	if(Tine.Messenger.VideoChat.hided){

	    _box.setWidth(Tine.Messenger.VideoChat.originalChatWidth + Tine.Messenger.VideoChat.flashVideoWidth);

	    _box.getComponent('messenger-chat-videochat').getEl().setWidth(Tine.Messenger.VideoChat.flashVideoWidth);
	    _box.doLayout();

	    Tine.Messenger.VideoChat.hided = false;
	}
	
    },
    
    hideVideoChat : function(_box){
	console.debug('begin hideVideoChat');
	if(!Tine.Messenger.VideoChat.hided){
	    _box.setWidth(Tine.Messenger.VideoChat.originalChatWidth);

	    _box.getComponent('messenger-chat-videochat').getEl().setWidth(0);

	    _box.doLayout();

	    Tine.Messenger.VideoChat.hided = true;
	}
	
    },
    unloadVideoChat: function(_box){
	//Ext.getCmp('messenger-chat-videochat').removeAll();
	_box.getComponent('messenger-chat-videochat').removeAll();
	_box.doLayout();
    }
    
};