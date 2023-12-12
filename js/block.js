import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const settings = window.wc.wcSettings.getSetting( 'blockonomics_data', {} );

const defaultLabel = __( 'Bitcoin', 'blockonomics-bitcoin-payments' );
const label = decodeEntities( settings.title ) || defaultLabel;

const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

const Content = () => {
	return decodeEntities( settings.description || '' );
};

const canMakePayment = () => {
	return true;
};

const blockonomicsPaymentMethod = {
    name: 'blockonomics',
    label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment,
	ariaLabel: label
}

registerPaymentMethod( blockonomicsPaymentMethod );