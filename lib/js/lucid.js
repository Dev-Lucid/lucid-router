
var lucid = {
	'jserrors':{},
    'requests':{},
    'formRules':{},
    'contentAreaLastReplace':{}, 
    'handlers':{
		'validateFail':function(field,errors){
			$(field).parent('.form-group').addClass('has-error');
			console.log('showing fail on '+field.name);
			
		},
		'validatePass':function(field){
			console.log('showing pass on '+field.name);
			$(field).parent('.form-group').removeClass('has-error');
		},
		'validators':{},
		'sendAnalytics':function(newUrl){
			if(typeof(ga) == 'function'){
				ga('send', 'pageview', {'page': newUrl});
			}
		},
        'ajaxError':function(response,message,errorDetails){
            console.log('Ajax failure: '+message);
        },
        'ajaxSuccess':function(response,status,request){
			var start_time = response.start_time;
            if(response.success !== true){
                lucid.handlers.ajaxError(response,response.message,response)
            }else{
                try{
					for(var key in response.append)
						$(key).append(response.append[key]);
					for(var key in response.prepend)
						$(key).prepend(response.prepend[key]);
					for(var key in response.replace){
						//lucid.contentAreaLastReplace[key] = 5;
						if(typeof(lucid.contentAreaLastReplace[key]) != 'number')
							lucid.contentAreaLastReplace[key] = 0;
						if(response.end_time > lucid.contentAreaLastReplace[key]){
							lucid.contentAreaLastReplace[key] = response.end_time;
							$(key).html(response.replace[key]);
						}else{
							console.log('did NOT update '+key+', request received out of order');
						}
					}
					if(typeof(response.title) == 'string')
						$('title').html(response.title);
					if(typeof(response.keywords) == 'string')
						$('meta[name=keywords]').attr('keywords', response.keywords);
					if(typeof(response.description) == 'string')
						$('meta[name=description]').attr('description', response.description);
				}
				catch(e){
				}
				try{
					if(response.javascript !== '')
						eval(response.javascript);
				}
				catch(e){
					var idx = 'error_'+(new Date().valueOf());
					lucid.jserrors[idx] = response.javascript;
					console.log('Error in javascript eval on line '+e.line+': '+e.message);					
					console.log('To retrieve full source of executed code: lucid.jserrors.'+idx);
					lucid.request('lucid_jserror/record_error',{
						'line':e.line,
						'message':e.message,
						'source':response.javascript
					});
				}
				
            }
        }
    }
};

lucid.request=function(todo,data,sendAnalytics,changeHandlerType){
	if(sendAnalytics === true){
		lucid.handlers.sendAnalytics(todo);
	}
    var timestamp = (new Date()).valueOf();
	jQuery.ajax(
		'app.php?todo='+todo+'&_time='+timestamp,{
			'success':(typeof(changeHandlerType)=='undefined')?lucid.handlers.ajaxSuccess:changeHandlerType,
			'error':(typeof(changeHandlerType)=='undefined')?lucid.handlers.ajaxError:changeHandlerType,
			'data':data,
			'dataType':'json',
            'type':'POST'
		}
	);
};

lucid.handleHashchange=function(){
	var newUrl = new String(window.location.hash).replace('#!','');
	lucid.request(newUrl,{},true);
}

lucid.submit=function(formObj){
    var data = {}
    for(var i=0;i<formObj.elements.length;i++){
        // gather some basic info about the element
        var elem = $(formObj.elements[i]);
        var type = elem.attr('type');
        var tag  = elem.prop('tagName');
        
        // handle by id if name is empty
        var name = (typeof(elem.attr('name')) != 'string')?elem.attr('id'):elem.attr('name');
    
        // flatten these a bit since jquery will make them behave the same via val();
        if(tag == 'SELECT'){
            type = 'text';
        }
        if(tag == 'TEXTAREA'){
            tag  = 'INPUT';
            type = 'text';
        }
        type = (type=='password')?'text':type;
        
        // only want elements with a name
        if(name != ''){
            // there are really only 3 diff kinds of fields in terms of how we handle values
            switch(type){
                case 'text':
                    data[name] = elem.val();
                    break;
                case 'radio':
                    if(elem.is(':checked'))
                        data[name] = elem.val();
                    break;
                case 'checkbox':
                    data[name] = elem.is(':checked');
                    break;
            }
        }
    }
    lucid.request($(formObj).attr('action'),data,true);
    return false;
};

lucid.bindFormValidation=function(formName,rules){
	console.log('attempting to bind form validation rules to form '+formName);
	lucid.formRules[formName] = rules;
	
	var elemsAssigned = {};
	for(var i=0;i<rules.length;i++){
		if(elemsAssigned[rules[i].name] !== true){
			elemsAssigned[rules[i].name] = true;
			$(document.forms[formName][rules[i].name]).blur(lucid.validateCheck);
		}
	}
}

lucid.validateCheck=function(){
	var obj    = $(this);
	var form   = this.form.name;
	var passes = true;
	var errors = [];
	console.log('validateCheck called on '+obj.attr('name'));
	for(var i=0;i<lucid.formRules[form].length;i++){
		if(lucid.formRules[form][i].name == this.name){
			if(typeof(lucid.handlers.validators[lucid.formRules[form][i].type]) == 'function'){
				var singlePass = lucid.handlers.validators[lucid.formRules[form][i].type](this.value,lucid.formRules[form][i],this);
				if(!singlePass){
					passes = false;
					errors.push(lucid.formRules[form][i].message);
				}
			}
		}
	}
	if(passes){
		lucid.handlers.validatePass(this);
	}else{
		lucid.handlers.validateFail(this,errors);
	}
	
}

lucid.handlers.validators.email=function(value,rule,field){
	value = new String(value);
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

lucid.handlers.validators.password=function(value,rule,field){
	return /^[a-z0-9_-]{6,18}$/.test(value);
}
lucid.handlers.validators.min_length=function(value,rule,field){
	return (new String(value).length >= rule.min_length);
}
lucid.handlers.validators.max_length=function(value,rule,field){
	return (new String(value).length <= rule.max_length);
}

lucid.handlers.validators.is_checked=function(value,rule,field){
	return field.checked;
}

window.onhashchange = lucid.handleHashchange;
if(new String(location.hash)+'' !== ''){
    lucid.handleHashchange();
}else{
    window.location.hash = '#!home/index';
}
//