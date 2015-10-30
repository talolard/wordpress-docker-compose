(function($) {
   
    //Copied from tinymce source because it was privately scoped 
    function cloneFormats(node) {
		var clone, temp, inner;

		do {
			if (/^(SPAN|STRONG|B|EM|I|FONT|STRIKE|U)$/.test(node.nodeName)) {
				if (clone) {
					temp = node.cloneNode(false);
					temp.appendChild(clone);
					clone = temp;
				} else {
					clone = inner = node.cloneNode(false);
				}

				clone.removeAttribute('id');
			}
		} while (node = node.parentNode);

		if (clone)
			return {wrapper : clone, inner : inner};
	};
    

    window.emc2_tinymce_init = function(ed) {
        
        ed.onNodeChange.add(function(ed, cm, n, co, ob) {
            //This fixes a bug in the Format dropdown
            //now when the cursor lands on an unwrapped piece
            function getParent(name) {
                //copied from tinymce source because it was privately scoped
				var i, parents = ob.parents, func = name;

				if (typeof(name) == 'string') {
					func = function(node) {
						return node.nodeName == name;
					};
				}

				for (i = 0; i < parents.length; i++) {
					if (func(parents[i]))
						return parents[i];
				}
			};
            
            if (c = cm.get('formatselect')) {
                p = getParent(tinymce.DOM.isBlock);
                if (p) {
                    c.select(p.nodeName.toLowerCase());
                }
                else {
                    c.select(''); //BUGFIX: select the 'format' element
                }
            }
        }, ed);
        
        if (ed.settings.force_p_newlines == false) {
            //When force_p_newlines is disabled we recreate the functionality from tinymce here to allow for br only inserts.
            var prev_key = false;
            
            ed.onKeyDown.add(function(ed, e) {
                //keyPress doesn't capture backspace so use keydown isntead
                if (e.keyCode != 13 || e.shiftKey) prev_key = false;
                
                if (tinymce.isGecko) {
                    if ((e.keyCode == 8 || e.keyCode == 46) && !e.shiftKey)
                        ed.forceBlocks.backspaceDelete(e, e.keyCode == 8);
                }
            });
            
            if (tinymce.isIE) {
                tinymce.addUnload(function() {
                    ed.forceBlocks._previousFormats = 0; // Fix IE leak
                });
            }
            
            ed.onKeyPress.add(function(ed, e) {
                //Bug fix, since we removed the p tag 
                if (e.keyCode == 13 && !e.shiftKey) {
                    if (prev_key && ed.settings.force_hybrid_newlines == true) {
                        //double return
                        //when a user enters two consecutive newlines in the wysiwyg editor, inject a new paragraph tag instead of the br tags*
                        //*Except in firefox where that functionality could not be implemented in a timely manner due to some browser bugs
                        if (!tinymce.isIE && !tinymce.isGecko) {
                            if (!ed.forceBlocks.insertPara(e)) {
                                tinymce.dom.Event.cancel(e);
                            }
                        }
                        else if (tinymce.isIE) {
                            ed.forceBlocks._previousFormats = 0;
                            
                            // Clone the current formats, this will later be applied to the new block contents
                            if (ed.selection.isCollapsed() && ed.settings.keep_styles) {
                                ed.forceBlocks._previousFormats = cloneFormats(ed.selection.getStart());
                            }
                            
                            //remove the last br tag that was inserted by the first enter key press
                            var sel = ed.selection.getStart();
                                sel.innerHTML = sel.innerHTML.replace(/<br\/?>([^<br\/?>]*)$/i, '');
                        }
                        
                        prev_key = false;
                    }
                    else {
                        prev_key = true;
                        
                        if ((tinymce.isIE || tinymce.isGecko) && e.keyCode == 13 && ed.selection.getNode().nodeName != 'LI') {
                            ed.selection.setContent('<br id="__" /> ', {format : 'raw'});
                            var n = ed.dom.get('__');
                            n.removeAttribute('id');
                            ed.selection.select(n);
                            ed.selection.collapse();
                            return tinymce.dom.Event.cancel(e);
                        }
                        
                        if (tinymce.isWebKit) {
                            
                            function insertBr(ed) {
                                var rng = ed.selection.getRng(), br, div = ed.dom.create('div', null, ' '), divYPos, vpHeight = ed.dom.getViewPort(ed.getWin()).h;
            
                                // Insert BR element
                                rng.insertNode(br = ed.dom.create('br'));
            
                                // Place caret after BR
                                rng.setStartAfter(br);
                                rng.setEndAfter(br);
                                ed.selection.setRng(rng);
            
                                // Could not place caret after BR then insert an nbsp entity and move the caret
                                if (ed.selection.getSel().focusNode == br.previousSibling) {
                                    ed.selection.select(ed.dom.insertAfter(ed.dom.doc.createTextNode('\u00a0'), br));
                                    ed.selection.collapse(true);
                                }
            
                                // Create a temporary DIV after the BR and get the position as it
                                // seems like getPos() returns 0 for text nodes and BR elements.
                                ed.dom.insertAfter(div, br);
                                divYPos = ed.dom.getPos(div).y;
                                ed.dom.remove(div);
            
                                // Scroll to new position, scrollIntoView can't be used due to bug: http://bugs.webkit.org/show_bug.cgi?id=16117
                                if (divYPos > vpHeight) // It is not necessary to scroll if the DIV is inside the view port.
                                    ed.getWin().scrollTo(0, divYPos);
                            }
                            
                            if (e.keyCode == 13 && !ed.dom.getParent(ed.selection.getNode(), 'h1,h2,h3,h4,h5,h6,ol,ul')) {
                                insertBr(ed);
                                tinymce.dom.Event.cancel(e);
                            }                        
                        }
                    }
                }
            });
            
            if (tinymce.isIE) {
                ed.onKeyUp.add(function(ed, e) {
                    // Let IE break the element and the wrap the new caret location in the previous formats
                    if (e.keyCode == 13 && !e.shiftKey) {
                        var parent = ed.selection.getStart(), fmt = ed.forceBlocks._previousFormats;
        
                        // Parent is an empty block
                        if (!parent.hasChildNodes() && fmt) {
                            parent = ed.dom.getParent(parent, ed.dom.isBlock);
        
                            if (parent && parent.nodeName != 'LI') {
                                parent.innerHTML = '';
        
                                if (ed.forceBlocks._previousFormats) {
                                    parent.appendChild(fmt.wrapper);
                                    fmt.inner.innerHTML = '\uFEFF';
                                } else {
                                    parent.innerHTML = '\uFEFF';
                                }
        
                                selection.select(parent, 1);
                                selection.collapse(true);
                                ed.getDoc().execCommand('Delete', false, null);
                                ed.forceBlocks._previousFormats = 0;
                            }
                        }
                    }
                });
            }
        }
    };

    window.emc2pm_fix_content = function(post_type) {
        //used on the writting settings page for fixing legacy content
        if (confirm('Are you sure? This process is not reversable')) {
            $.get(
                ajaxurl, 
                {
                    nonce : emc2pm.fix_content_nonce,
                    action : 'emc2pm_fix_posts',
                    post_type : post_type
                },
                function(r) {
                    alert(r);
                }
            );
        }
        return false;
    };

    function fix_intra_tag_content(needle, haystack, placeholder, correct) {
        //This will encode intra tag & intra quote content to prevent TinyMCE escaping
        var repeat = true;
        
        //look for needle (improperly encoded strings) in haystack (content inside valid html tags)
        var regescape = new RegExp("(<[^>]*)(" + needle + ")([^>]*>)", "g");
        while (repeat) {
            //replace with a placeholder value because replacing with the correct value would prevent downstream replacements
            newstack = haystack.replace(regescape, "$1" + placeholder + "$3");
            //repeat process until transformations stop
            if (newstack == haystack) repeat = false;
            haystack = newstack;
        }
        
        //finally replace placeholder values with correct value (proper, unencoded strings);
        if (correct) {
            var regfix = new RegExp(placeholder, "g");
            return haystack.replace(regfix, correct);
        }
        else {
            //<, > will need to be replaced after all other replacements
            return haystack;
        }
    }
    
    //on dom load
    $(function() {
        //first_switch_html & orig_title are used in the bugfix in the afterPreWpautop override below
        var first_switch_html = true;
        var orig_title = $('#post #title').val();
        
        //disable evil... yeah that's right I said EVIL!
        if (window.switchEditors) {
            switchEditors._wp_Nop = function(e) { return e; };
            switchEditors._wp_Autop = function(e) { return e; };
        }

        if (window.edButtons) {
            //Emphasis and Strong have very special meanings, and they DO NOT mean bold and italics people!!!
            edButtons[0] = new edButton('ed_strong','b','<b>','</b>','b');
            edButtons[1] = new edButton('ed_em','i','<i>','</i>','i');
        }

        $('body').bind('afterPreWpautop', function(e, o) {
            //On Switch to HTML & On save/update from Visual tab
            //Now we replace all those temporary html comments with spaces and newlines
            o.data = o.unfiltered;
           
            //remove cdata tags that are injected by browser, they can also malform mep tags
            o.data = o.data.replace(/\/\/ <!\[CDATA\[/g, "");
            o.data = o.data.replace(/[^-]mep-nl-->/g, "<!--mep-nl-->"); //fix new lines before and after script tags
            o.data = o.data.replace(/<!--mep-nl[^-]/g, "<!--mep-nl-->"); 
            o.data = o.data.replace(/<!--mep-tab[^-]/g, "<!--mep-tab-->"); //corner case, user adds four spaces after an opening script tag... not likely
            o.data = o.data.replace(/[^-]mep-tab-->/g, "<!--mep-tab-->");  //fix script blocks that are fully indented
            o.data = o.data.replace(/\/\/ \]\]>/g, "");
            
            //now decode newlines and tabs
            o.data = o.data.replace(/<\!--mep-nl-->/g, "\r\n").replace(/<\!--mep-tab-->/g, "    ");
            
            //Fix broken >, <, &, etc symbols when they exist inside quote marks inside tag elements
            o.data = fix_intra_tag_content("&amp;", o.data, "{-mep-amp}", "&");
            o.data = fix_intra_tag_content("&gt;", o.data, "{-mep-gts}");
            o.data = fix_intra_tag_content("&lt;", o.data, "{-mep-lts}");
            o.data = fix_intra_tag_content("&#8221;", o.data, "{-mep-dbl-quote}", '"'); 
            o.data = fix_intra_tag_content("&#8243;", o.data, "{-mep-dbl-quote}", '"');
            
            //fix >, < last because `fix_intra_tag_content` relies on their symbol placement
            o.data = o.data.replace(/{-mep-gts}/g, '>');
            o.data = o.data.replace(/{-mep-lts}/g, '<');
            
            //And finally remove the code tag hack from the markup
            o.data = o.data.replace(/<code style=['"]display: none;['"]><!--[\s\S]*?--><\/code>/g, function(s) {
                return s.substring(s.indexOf('<!--'), s.indexOf('-->') + 3);
            });
            
            if (first_switch_html) {
                first_switch_html = false;
                /*BUGFIX: in autosave.js, on document load, the textarea value is cached in a js var 'autosaveLast' and
                  is used to determine if changes were made in onbeforeunload. The content is correct when we start in
                  HTML mode.  But when we load the page in Visual mode it's "mep" encoded (properly so to render correctly.)
                  So loading in Visual mode and switching to HTML mode will result in comparing the "mep" encoded version
                  with the proper clean HTML version.  So on the very first transition we update the "autosaveLast" var
                  value to the clean HTML content.  It's incredibly fortunate that not only is this variable publicly scoped
                  against JS best practices, but also that in Visual mode tinymce uses a different variable to check for
                  changes.  So adjusting autosaveLast will not affect the onbeforeunload event in Visual mode.
                */
                
                //only adjust the html if the visual tab isn't "dirty" because then it doesn't matter either way
                //but if they made changes on the visual tab a tab switch would use those updates and the editor
                //wouldn't alert the user to any changes on refresh.
                if (tinyMCE && tinyMCE.activeEditor && tinyMCE.activeEditor.isDirty() == false) {
                    autosaveLast = orig_title + o.data.replace(/\r/g, ''); //seems \r's are stripped out of textarea#content
                                                                           //maybe I shouldn't be including them, thought they
                                                                           //were necessary for windows platforms
                }
            }            
        }).bind('afterWpautop', function(e, o) {
            //On Switch to Visual
            first_switch_html = false; //this var is only important if we start on the visual tab
                                       //TODO: but a bug exists in wordpress that could be fixed here.  Basically if you
                                       //load with HTML tab active, make a change, switch to Visual, and click Refresh
                                       //you won't be prompted to save changes because the Visual tab sets originalContent
                                       //to the current html value on tab switch. :(  So we could overwrite it with the
                                       //true originalContent here on first switch.
            
            //first: hack fix for preserving multi-line html comments
            o.unfiltered = o.unfiltered.replace(/<!--[\s\S]*?-->/g, function(s) {
                return "<code style='display: none;'>" + s + "</code>";
            });

            //next: preserve newlines and whitespace in pre & code tags because the browser will not mess with those
            if ( o.unfiltered.indexOf('<pre') != -1 || o.unfiltered.indexOf('<code') != -1 ) {
                o.unfiltered = o.unfiltered.replace(/<(pre|code)[^>]*>[\s\S]+?<\/\1>/g, function(s) {
                    return s.replace(/(\r\n|\n)/g, '<mep-preserve-nl>').replace(/\t/g, "<mep-preserve-tab>").replace(/\s/g, "<mep-preserve-space>");
                });
            }

            //now: replace any newline characters with a custom mep html comment as a marker for where
            //newline chars should be added back in when we're done
            o.data = o.unfiltered.replace(/(\r\n|\n)/g, "<!--mep-nl-->").replace(/(\t|\s\s\s\s)/g, "<!--mep-tab-->");

            //finally: restore the whitespace back in pre & code tags
            o.data = o.data.replace(/<mep-preserve-nl>/g, "\n").replace(/<mep-preserve-tab>/g, "\t").replace(/<mep-preserve-space>/g, " ");
            
            //BUGFIX: add space between quotes and closing tags
            o.data = o.data.replace(/"\/>/g, '" />');
        });
    });
})(jQuery);