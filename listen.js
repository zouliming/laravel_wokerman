var ws = new WebSocket('ws://websocket_server:port');
            ws.onopen = function(){
            var uid = 'xxxx';
            ws.send(uid);
        };
        ws.onmessage = function(e){
            alert(e.data);
        };