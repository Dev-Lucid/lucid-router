
var lucid = {
	'config':{
		'contentDisplayMode':'#full-width',
		'displayModeTriggers':{
			'#full-width':['#full-width'],
			'#split':['#left','#center']
		},
		'navState':{}
	},
	'jserrors':{},
    'requests':{},
    'formRules':{},
    'contentAreaLastReplace':{}, 
    'handlers':{
		'validateFail':function(field,errors){
			jQuery(field).parent('.form-group').addClass('has-error');
			console.log('showing fail on '+field.name);
			
		},
		'validatePass':function(field){
			console.log('showing pass on '+field.name);
			jQuery(field).parent('.form-group').removeClass('has-error');
		},
		'validators':{},
		'setNavState':function(area,newState){
			lucid.config.navState[area] = newState;
		},
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
					
					for(var selector in response.append){
						jQuery(selector).append(response.append[selector]);
					}
					for(var selector in response.prepend){
						jQuery(key).prepend(response.prepend[selector]);
					}
					
					var newMode = false;
					for(var selector in response.replace){
						
						var thisMode = lucid.handlers.checkContentDisplayMode(selector)
						if(thisMode !== false){
							newMode = thisMode;
						}
						
						if(typeof(lucid.contentAreaLastReplace[selector]) != 'number')
							lucid.contentAreaLastReplace[selector] = 0;
						if(response.end_time > lucid.contentAreaLastReplace[selector]){
							lucid.contentAreaLastReplace[selector] = response.end_time;
							jQuery(selector).html(response.replace[selector]);
						}else{
							console.log('did NOT update '+selector+', request received out of order');
						}
					}
					if(typeof(response.title) == 'string')
						jQuery('title').html(response.title);
					if(typeof(response.keywords) == 'string')
						jQuery('meta[name=keywords]').attr('keywords', response.keywords);
					if(typeof(response.description) == 'string')
						jQuery('meta[name=description]').attr('description', response.description);
					
					if(newMode !== false && newMode != lucid.config.contentDisplayMode){
						lucid.handlers.changeDisplayMode(lucid.config.contentDisplayMode,newMode);
						lucid.config.contentDisplayMode = newMode;
					}
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
        },
        'changeDisplayMode':function(oldMode,newMode){
			console.log('changing display mode to '+newMode+' from '+oldMode);
			jQuery(oldMode).fadeOut(100);
			jQuery(newMode).fadeIn(500);			
		},
		'checkContentDisplayMode':function(selector){
			for(var triggerArea in lucid.config.displayModeTriggers){
				var selectors = lucid.config.displayModeTriggers[triggerArea]
				for(var i=0;i<selectors.length;i++){
					if(selectors[i] == selector){
						return triggerArea;
					}
				}
			}
			return false;
		}
    }
};


lucid.request=function(todo,data,sendAnalytics,changeHandlerType){
	if(sendAnalytics === true){
		lucid.handlers.sendAnalytics(todo);
	}
    var timestamp = (new Date()).valueOf();
    if(typeof(data) != 'object'){
		data = {};
	}
	data['navState'] = lucid.config.navState;
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
	console.log(arguments);
	var newLink = jQuery('body').find('a[href=\''+window.location.hash+'\']');
	newLink.each(function(){
		var thisLink = jQuery(this);
		var curLink = thisLink.parent().parent().find('li.active');
		curLink.each(function(){
			jQuery(this).removeClass('active');
		});
		thisLink.parent().addClass('active');
	});
	console.log('link that initiated: '+newLink.html());
	var newUrl = new String(window.location.hash).replace('#!','');
	lucid.request(newUrl,{},true);
}

lucid.submit=function(formObj){
    var data = {}
    for(var i=0;i<formObj.elements.length;i++){
        // gather some basic info about the element
        var elem = jQuery(formObj.elements[i]);
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
    lucid.request(jQuery(formObj).attr('action'),data,true);
    return false;
};

lucid.bindFormValidation=function(formName,rules){
	console.log('attempting to bind form validation rules to form '+formName);
	lucid.formRules[formName] = rules;
	
	var elemsAssigned = {};
	for(var i=0;i<rules.length;i++){
		if(elemsAssigned[rules[i].name] !== true){
			elemsAssigned[rules[i].name] = true;
			jQuery(document.forms[formName][rules[i].name]).blur(lucid.validateCheck);
		}
	}
}

lucid.validateCheck=function(){
	var obj    = jQuery(this);
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
    window.location.hash = '#!static_content/index';
}
