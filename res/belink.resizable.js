
if (! Belink) var Belink = {};
Belink.Resizable = Class.create();
Object.extend(Belink.Resizable, {
	defaultOptions: {
		elementClassName: 'belink-resizable-element',
		wrapperClassName: 'belink-resizable-wrapper',
		minWidth: 13,
		minHeight: 13,
		cornerClassName: 'belink-resizable-corner',
		cornerStyle: {
			height: '13px',
			width: '13px',
			backgroundImage: 'url(resizable-corner.png)',
			position: 'absolute',
			bottom: '0',
			right: '0',
			cursor: 'nw-resize'
		}
	},

	_current: null,
	_X: 0,
	_Y: 0,

	initDrag: function(instance, event) {
		if (this._current) Belink.Resizable.releaseDrag();
		this._current = instance;
		this._X = Event.pointerX(event);
		this._Y = Event.pointerY(event);

		Event.observe(document.documentElement, 'mousemove', this.followDrag.bindAsEventListener(this));
		Event.observe(document.documentElement, 'mouseup', this.releaseDrag.bindAsEventListener(this));
	},

	followDrag: function(event) {
		if (! this._current) return;

		var diffX = Event.pointerX(event) - this._X;
		var diffY = Event.pointerY(event) - this._Y;

		this._X = Event.pointerX(event);
		this._Y = Event.pointerY(event);

		this._current.resizeBy(diffX, diffY);
	},

	releaseDrag: function() {
		Event.stopObserving(document.documentElement, 'mousemove', this.followDrag);
		Event.stopObserving(document.documentElement, 'mouseup', this.releaseDrag);
		this._current = null;
	}
});

Belink.Resizable.prototype = {
	options: null,
	element: null,
	wrapper: null,
	corner:  null,
	resizable: true,
	initialize: function(element, options) {
		this.element = $(element);
		this.options = Belink.Resizable.defaultOptions;
		if (options) Object.extend(this.options, options);

		this.wrapper = $(document.createElement('DIV'));
		this.corner = $(document.createElement('DIV'));

		this.element.parentNode.replaceChild(this.wrapper, this.element);

		this.wrapper.appendChild(this.element);
		this.wrapper.appendChild(this.corner);

		this.element.addClassName(this.options.elementClassName);
		this.wrapper.className = this.options.wrapperClassName;

		var w = this.element.getWidth();
		if (! w) w = this.element.getStyle('width');
		if (! w) w = this.options.minWidth;
			
		var h = this.element.getHeight();
		if (! h) h = this.element.getStyle('height');
		if (! h) h = this.options.minHeight;

		this.wrapper.setStyle({
			width:  parseInt(w) + 'px',
			height: parseInt(h) + 'px',
			position: 'relative'
		}, true);

		this.element.setStyle({position: 'absolute',
			top: '0', right: '0', bottom: '0', left: '0',
			margin: '0', width: '100%', height: '100%'
		}, true);

		this.corner.className = this.options.cornerClassName;
		this.corner.setStyle(this.options.cornerStyle, true);
		Event.observe(this.corner, 'mousedown', this.startDrag.bindAsEventListener(this));
	},
	startDrag: function(event) {
		if (this.resizable) Belink.Resizable.initDrag(this, event);
	},
	enable: function() {
		this.resizable = true;
		this.corner.show();
	},
	disable: function() {
		this.resizable = false;
		this.corner.hide();
	},
	resizeBy: function(diffX, diffY) {
		this.resizeTo(
			parseInt(this.wrapper.style.width)  + diffX,
			parseInt(this.wrapper.style.height) + diffY
		);
	},
	resizeTo: function(width, height) {
		this.wrapper.style.width  = Math.max((parseInt(width)), this.options.minWidth) + 'px';
		this.wrapper.style.height = Math.max((parseInt(height)), this.options.minHeight) + 'px';
	}
}

if(navigator.userAgent.toLowerCase().indexOf('safari/') > -1;){
	Belink.Resizable = function (){
		
	}
}