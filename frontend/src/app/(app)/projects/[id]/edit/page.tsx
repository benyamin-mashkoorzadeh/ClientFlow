"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Project } from "@/lib/types";
import { PageHeader } from "@/components/page-header";
import { ProjectForm } from "@/components/project-form";

export default function EditProjectPage() { const { id } = useParams<{ id: string }>(); const [project, setProject] = useState<Project | null>(null); const [error, setError] = useState<string | null>(null); useEffect(() => { void api.project(id).then((response) => setProject(response.data)).catch((cause: unknown) => setError(cause instanceof ApiError ? cause.message : "The project could not be loaded.")); }, [id]); if (error) return <div className="notice notice-warning"><strong>Couldn’t load project</strong><span>{error}</span><Link href={`/projects/${id}`} className="text-button">Back to project</Link></div>; if (!project) return <div className="screen-state"><span className="spinner" />Loading project...</div>; return <><PageHeader eyebrow="Project profile / Edit" title="Edit project" description={`Update the details for ${project.name}.`} /><section className="form-panel"><ProjectForm project={project} /></section></>; }