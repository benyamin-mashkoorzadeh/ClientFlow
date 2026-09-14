import { ClientForm } from "@/components/client-form";
import { PageHeader } from "@/components/page-header";

export default function NewClientPage() { return <><PageHeader eyebrow="Client relationships / New" title="Add a client" description="Keep the important details close to the work." /><section className="form-panel"><ClientForm /></section></>; }